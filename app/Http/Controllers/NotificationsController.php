<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $rawNotifications = $user->notifications()->latest()->get();

        $user->unreadNotifications()->update(['read_at' => now()]);

        $actorIds = $rawNotifications
            ->pluck('data.actor_id')
            ->filter()
            ->unique();

        $actors = User::whereIn('id', $actorIds)
            ->get(['id', 'name', 'username', 'avatar'])
            ->keyBy('id');

        $followActorIds = $rawNotifications
            ->filter(fn ($n) => ($n->data['type'] ?? '') === 'follow')
            ->pluck('data.actor_id')
            ->filter()
            ->unique();

        $alreadyFollowing = $user->follows()
            ->whereIn('following_id', $followActorIds)
            ->pluck('following_id')
            ->flip();

        $notifications = $rawNotifications->map(function ($n) use ($actors, $alreadyFollowing) {
            $data = $n->data;
            if (isset($data['actor_id']) && $actors->has($data['actor_id'])) {
                $actor = $actors->get($data['actor_id']);
                $data['actor_name'] = $actor->name;
                $data['actor_avatar'] = $actor->avatar_url;
            }

            return [
                'id' => $n->id,
                'type' => $data['type'] ?? 'unknown',
                'data' => $data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
                'is_following_actor' => isset($data['actor_id']) && $alreadyFollowing->has($data['actor_id']),
            ];
        });

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->markAsRead();

        return back();
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->where('id', $id)->first()?->markAsRead();

        return back();
    }

    public function clearAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }
}
