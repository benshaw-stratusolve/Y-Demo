<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $user->loadCount(['followers', 'follows', 'posts']);

        $authUser = auth()->user();
        $isFollowing = $authUser->follows()->where('following_id', $user->id)->exists();
        $isOwnProfile = $authUser->id === $user->id;
        $tab = $request->query('tab', 'posts');

        if ($tab === 'replies') {
            $posts = $user->posts()
                ->whereNotNull('body')
                ->whereNotNull('parent_post_id')
                ->with('parent:id,body,user_id', 'parent.user:id,name,username')
                ->withCount(['likes', 'replies'])
                ->latest()
                ->paginate(10);
        } else {
            $posts = $user->posts()
                ->whereNotNull('body')
                ->whereNull('repost_of_id')
                ->whereNull('parent_post_id')
                ->withCount(['likes', 'replies'])
                ->latest()
                ->paginate(10);
        }

        return Inertia::render('users/Show', [
            'profileUser' => $user,
            'posts' => $posts,
            'isFollowing' => $isFollowing,
            'isOwnProfile' => $isOwnProfile,
            'activeTab' => $tab,
        ]);
    }

    public function postsJson(Request $request, User $user): JsonResponse
    {
        $tab = $request->query('tab', 'posts');

        if ($tab === 'replies') {
            $posts = $user->posts()
                ->whereNotNull('body')
                ->whereNotNull('parent_post_id')
                ->with('parent:id,body,user_id', 'parent.user:id,name,username')
                ->withCount(['likes', 'replies'])
                ->latest()
                ->paginate(10);
        } else {
            $posts = $user->posts()
                ->whereNotNull('body')
                ->whereNull('repost_of_id')
                ->whereNull('parent_post_id')
                ->withCount(['likes', 'replies'])
                ->latest()
                ->paginate(10);
        }

        return response()->json($posts);
    }
}
