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

test('chat endpoint returns gemini response', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Hello from Gemini!']]]],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), [
            'message' => 'Hello FlockAI',
            'history' => [],
        ]);

    $response->assertOk()->assertJsonPath('message', 'Hello from Gemini!');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com') &&
        str_contains($request->url(), config('services.gemini.model'))
    );
});

test('chat endpoint passes history to gemini', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Got your history!']]]],
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

    Http::assertSent(fn ($request) => count($request['contents']) === 3 // 2 history + current user
    );
});

test('chat endpoint maps assistant role to model for gemini', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'OK!']]]],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), [
            'message' => 'Hi',
            'history' => [
                ['role' => 'assistant', 'content' => 'Hello!'],
            ],
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => $request['contents'][0]['role'] === 'model'
    );
});

test('chat endpoint returns error message when gemini api fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), ['message' => 'Hello'])
        ->assertStatus(500)
        ->assertJsonPath('message', "Sorry, I'm having trouble connecting right now. Please try again.");
});
