<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('chat endpoint requires authentication', function () {
    $this->post(route('flock-ai.chat'), ['message' => 'Hello'])
        ->assertRedirect(route('login'));
});

test('chat endpoint validates message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('flock-ai.chat'), [])
        ->assertSessionHasErrors('message');
});

test('chat endpoint returns grok response', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hello from Grok!']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), [
            'message' => 'Hello FlockAI',
            'history' => [],
        ]);

    $response->assertOk()->assertJsonPath('message', 'Hello from Grok!');

    Http::assertSent(fn ($request) =>
        str_contains($request->url(), 'api.x.ai') &&
        $request['model'] === config('services.xai.model')
    );
});

test('chat endpoint passes history to grok', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Got your history!']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), [
            'message' => 'Follow up question',
            'history' => [
                ['role' => 'assistant', 'content' => 'I said something earlier.'],
                ['role' => 'user', 'content' => 'First question'],
            ],
        ])
        ->assertOk();

    Http::assertSent(fn ($request) =>
        count($request['messages']) === 4 // system + 2 history + current user
    );
});

test('chat endpoint returns error message when xai api fails', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([], 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), ['message' => 'Hello'])
        ->assertStatus(500)
        ->assertJsonPath('message', "Sorry, I'm having trouble connecting right now. Please try again.");
});
