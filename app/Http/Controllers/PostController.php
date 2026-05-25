<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesProfanityStrikes;
use App\Events\PostBroadcast;
use App\Events\PostInteractionUpdated;
use App\Jobs\ProcessPostImage;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentCreatedNotification;
use App\Notifications\CommentDeletedNotification;
use App\Notifications\PostDeletedNotification;
use App\Notifications\LikeNotification;
use App\Notifications\PostCreatedNotification;
use App\Notifications\ReplyNotification;
use App\Services\ProfanityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use HandlesProfanityStrikes;

    public function store(Request $request, ProfanityService $profanity): RedirectResponse
    {
        $request->validate([
            'body' => 'required|string|min:1|max:280',
            'image' => 'nullable|image|max:5120',
        ]);

        $user = auth()->user();

        if ($user->isBanned()) {
            return back()->withErrors(['account_banned' => 'Your account has been banned for repeated violations.']);
        }

        if ($request->filled('body') && $profanity->contains($request->body)) {
            return $this->handleStrike($user, 'Your post contains inappropriate language.');
        }

        $image = $request->hasFile('image')
            ? $request->file('image')->store('post-images', 'public')
            : null;

        $post = $user->posts()->create([
            'body' => $request->body,
            'image' => $image,
        ]);

        if ($image) {
            ProcessPostImage::dispatch($image);
        }

        $user->notify(new PostCreatedNotification($post));

        $user->followerUsers()->each(function (User $follower) use ($post) {
            broadcast(new PostBroadcast($post, $follower));
        });

        return to_route('dashboard');
    }

    public function like(Post $post): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isBanned()) {
            return back()->withErrors(['account_banned' => 'Your account has been banned.']);
        }

        $changes = $user->likedPosts()->toggle($post->id);

        if (! empty($changes['attached']) && $post->user_id !== $user->id) {
            $post->user->notify(new LikeNotification($user, Str::limit($post->body, 50)));
        }

        $post->loadCount(['likes', 'replies']);
        broadcast(new PostInteractionUpdated($post));

        return back();
    }

    public function repost(Post $post): RedirectResponse
    {
        abort_if($post->repost_of_id !== null, 422);

        $user = auth()->user();

        if ($user->isBanned()) {
            return back()->withErrors(['account_banned' => 'Your account has been banned.']);
        }
        $existing = $user->posts()->where('repost_of_id', $post->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $user->posts()->create(['repost_of_id' => $post->id]);
        }

        return back();
    }

    public function destroy(Post $post): RedirectResponse
    {
        abort_if($post->user_id !== auth()->id(), 403);

        $user = $post->user;
        $excerpt = $post->body ?? '';

        // Remove the orphaned "post published" notification for this post
        $user->notifications()
            ->where('type', PostCreatedNotification::class)
            ->whereJsonContains('data->post_id', $post->id)
            ->delete();

        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        if ($excerpt) {
            $notification = $post->parent_post_id
                ? new CommentDeletedNotification($excerpt)
                : new PostDeletedNotification($excerpt, selfDeleted: true);

            $user->notify($notification);
        }

        return back();
    }

    public function reply(Request $request, Post $post, ProfanityService $profanity): RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:280']);

        $user = auth()->user();

        if ($user->isBanned()) {
            return back()->withErrors(['account_banned' => 'Your account has been banned for repeated violations.']);
        }

        $rateLimitKey = 'reply:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return back()->withErrors(['reply_limit' => "You're commenting too fast. Try again in {$seconds} ".($seconds === 1 ? 'second' : 'seconds').'.']);
        }
        RateLimiter::hit($rateLimitKey, 300);

        if ($profanity->contains($request->body)) {
            return $this->handleStrike($user, 'Your reply contains inappropriate language.');
        }

        $comment = $user->posts()->create([
            'body' => $request->body,
            'parent_post_id' => $post->id,
        ]);

        $user->notify(new CommentCreatedNotification($comment, $post));

        if ($post->user_id !== $user->id) {
            $post->user->notify(new ReplyNotification($user, Str::limit($request->body, 50)));
        }

        $post->loadCount(['likes', 'replies']);
        broadcast(new PostInteractionUpdated($post));

        return back();
    }

    public function show(Post $post): Response
    {
        $post->load('user');
        $post->loadCount(['likes', 'replies']);
        $post->user->loadCount(['followers', 'follows', 'posts']);

        $user = auth()->user();
        $post->liked_by_user = $post->likes()->where('user_id', $user->id)->exists();
        $isFollowing = $user->follows()->where('following_id', $post->user_id)->exists();

        $replies = $post->replies()
            ->with('user')
            ->latest()
            ->paginate(15);

        $authorPosts = $post->user->posts()
            ->whereNotNull('body')
            ->whereNull('repost_of_id')
            ->whereNull('parent_post_id')
            ->where('id', '!=', $post->id)
            ->withCount(['likes', 'replies'])
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('posts/Show', [
            'post' => $post,
            'replies' => $replies,
            'isFollowing' => $isFollowing,
            'authorPosts' => $authorPosts,
        ]);
    }

    public function repliesJson(Post $post): \Illuminate\Http\JsonResponse
    {
        $replies = $post->replies()
            ->with('user')
            ->latest()
            ->paginate(15);

        return response()->json($replies);
    }
}
