<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\FollowNotification;
use Illuminate\Http\RedirectResponse;

class FollowController extends Controller
{
    public function toggle(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403);

        if (auth()->user()->isBanned()) {
            return back()->withErrors(['account_banned' => 'Your account has been banned.']);
        }

        $changes = auth()->user()->followedUsers()->toggle($user->id);

        if (! empty($changes['attached'])) {
            $user->notify(new FollowNotification(auth()->user()));
        }

        return back();
    }
}
