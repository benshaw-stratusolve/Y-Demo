<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Retrieve the real user.{id} callback registered by routes/channels.php.
// channels.php is loaded during app bootstrap (via withRouting(channels:...)),
// so by the time any test runs, Broadcast::channel('user.{id}', ...) has
// already been called and the closure is stored on the default broadcaster.
// Broadcast::getChannels() proxies via __call → driver()->getChannels().
function getUserChannelCallback(): Closure
{
    $channels = Broadcast::getChannels();
    $callback = $channels->get('user.{id}');

    if (! $callback instanceof Closure) {
        throw new RuntimeException(
            'user.{id} channel callback not found — ensure routes/channels.php registers it.'
        );
    }

    return $callback;
}

// ──────────────────────────────────────────────────────────────────────────────
// Direct callback tests — these exercise the REAL routes/channels.php closure.
// If the production authorization logic changes, these tests will catch it.
// ──────────────────────────────────────────────────────────────────────────────

test('channel callback grants access to own channel', function () {
    $user = User::factory()->create();
    $callback = getUserChannelCallback();
    expect((bool) $callback($user, $user->id))->toBeTrue();
});

test('channel callback denies access to another user channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $callback = getUserChannelCallback();
    expect((bool) $callback($user, $other->id))->toBeFalse();
});
