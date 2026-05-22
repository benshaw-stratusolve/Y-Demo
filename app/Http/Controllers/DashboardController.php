<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $followingIds = $user->follows()->pluck('following_id')->push($user->id);
        $following = User::whereIn('id', $followingIds)
            ->where('id', '!=', $user->id)
            ->get(['id', 'name', 'username', 'avatar'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'is_following' => true,
            ]);

        $isNewUser = $user->follows()->doesntExist();

        $postsQuery = Post::with(['user', 'replies.user'])
            ->withCount(['likes', 'replies', 'reposts'])
            ->whereNull('parent_post_id')
            ->whereNull('repost_of_id')
            ->whereNotNull('body')
            ->latest();

        if (! $isNewUser) {
            $postsQuery->whereIn('user_id', $followingIds);
        }

        $posts = $postsQuery->paginate(10);

        $trending = Inertia::defer(fn () => Post::withCount('likes')
            ->whereNotNull('body')
            ->orderByDesc('likes_count')
            ->with('user')
            ->limit(5)
            ->get());

        $topAccounts = Inertia::defer(fn () => User::withCount('followers')
            ->where('id', '!=', $user->id)
            ->orderByDesc('followers_count')
            ->limit(5)
            ->get(['id', 'name', 'username', 'avatar'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'is_following' => $followingIds->contains($u->id),
            ]));

        $postIds = $posts->pluck('id');

        $likedIds = Like::where('user_id', $user->id)
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')
            ->flip()
            ->all();

        $repostedIds = Post::where('user_id', $user->id)
            ->whereNotNull('repost_of_id')
            ->whereIn('repost_of_id', $postIds)
            ->pluck('repost_of_id')
            ->flip()
            ->all();

        $posts->through(function (Post $post) use ($likedIds, $repostedIds) {
            $post->liked_by_user = isset($likedIds[$post->id]);
            $post->reposted_by_user = isset($repostedIds[$post->id]);

            return $post;
        });

        return Inertia::render('Dashboard', [
            'posts' => $posts,
            'trending' => $trending,
            'following' => $following,
            'topAccounts' => $topAccounts,
            'isDiscoveryFeed' => $isNewUser,
            'activeTab' => $request->get('tab', 'forYou'),
        ]);
    }
}
