<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Pusher\Pusher;

beforeEach(function () {
    // Switch from the null driver (set in phpunit.xml) to reverb so that
    // channel callbacks are actually evaluated during auth.
    Config::set('broadcasting.default', 'reverb');
    Config::set('broadcasting.connections.reverb.key', 'testkey');
    Config::set('broadcasting.connections.reverb.secret', 'testsecret');
    Config::set('broadcasting.connections.reverb.app_id', '12345');
    Config::set('broadcasting.connections.reverb.options.host', 'localhost');
    Config::set('broadcasting.connections.reverb.options.port', 8080);
    Config::set('broadcasting.connections.reverb.options.scheme', 'http');
    Config::set('broadcasting.connections.reverb.options.useTLS', false);

    // Build a PusherBroadcaster with a mocked Pusher so authorizeChannel
    // does not need a running server — it just returns a fake token.
    $pusher = Mockery::mock(Pusher::class);
    $pusher->allows('authorizeChannel')->andReturn('{"auth":"testkey:fakesig"}');

    $broadcaster = new Illuminate\Broadcasting\Broadcasters\PusherBroadcaster($pusher);

    // Re-register our application channels on this broadcaster.
    $broadcaster->channel('user.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id;
    });

    // Replace the BroadcastManager so the controller uses our broadcaster.
    $manager = Mockery::mock(Illuminate\Broadcasting\BroadcastManager::class)->makePartial();
    $manager->allows('driver')->andReturn($broadcaster);
    $manager->allows('auth')->andReturnUsing(fn ($req) => $broadcaster->auth($req));

    app()->instance(Illuminate\Broadcasting\BroadcastManager::class, $manager);
    Broadcast::clearResolvedInstances();
    Broadcast::setFacadeApplication(app());
});

test('user can subscribe to their own private channel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-user.' . $user->id,
        'socket_id'    => '123.456',
    ])->assertOk();
});

test('user cannot subscribe to another user private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-user.' . $other->id,
        'socket_id'    => '123.456',
    ])->assertForbidden();
});
