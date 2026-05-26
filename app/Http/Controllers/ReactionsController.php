<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReactionsController extends Controller
{
    public function toggle(Request $request, Message $message): RedirectResponse
    {
        $user = auth()->user();
        $conversation = $message->conversation;
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $request->validate(['emoji' => 'required|string|max:10']);

        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $request->emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $message->reactions()->create(['user_id' => $user->id, 'emoji' => $request->emoji]);
        }

        return to_route('messages.show', $conversation);
    }
}
