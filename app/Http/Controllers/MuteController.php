<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class MuteController extends Controller
{
    public function toggle(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422);

        $auth = auth()->user();

        if ($auth->hasMuted($user->id)) {
            $auth->mutedUsers()->detach($user->id);
        } else {
            $auth->mutedUsers()->attach($user->id);
        }

        return back();
    }
}
