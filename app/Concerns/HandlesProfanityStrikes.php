<?php

namespace App\Concerns;

use App\Models\User;
use App\Notifications\ProfanityStrikeNotification;
use Illuminate\Http\RedirectResponse;

trait HandlesProfanityStrikes
{
    protected function handleStrike(User $user, string $message, string $errorField = 'body'): RedirectResponse
    {
        $user->increment('profanity_strikes');
        $user->refresh();

        if ($user->profanity_strikes >= 3) {
            $user->forceFill(['banned_at' => now()])->save();

            $banMessage = 'Your account has been banned for repeated use of inappropriate language.';
            $user->notify(new ProfanityStrikeNotification($banMessage, isBan: true));

            return back()->withErrors([$errorField => $banMessage]);
        }

        $remaining = 3 - $user->profanity_strikes;
        $strikeMessage = "{$message} Strike {$user->profanity_strikes} of 3 — {$remaining} remaining before your account is banned.";
        $user->notify(new ProfanityStrikeNotification($strikeMessage));

        return back()->withErrors([$errorField => $strikeMessage])->withInput();
    }
}
