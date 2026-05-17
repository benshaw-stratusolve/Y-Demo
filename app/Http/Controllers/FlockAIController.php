<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class FlockAIController extends Controller
{
    private const SYSTEM_PROMPT = 'You are FlockAI, an intelligent AI assistant embedded in Y — a modern social platform. Help users with content ideas, conversation starters, community building, trend analysis, and general questions. Be concise, friendly, and direct. Keep responses focused unless the user asks for detail.';

    public function index(): Response
    {
        return Inertia::render('FlockAI');
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ...$request->input('history', []),
            ['role' => 'user', 'content' => $request->input('message')],
        ];

        $response = Http::withToken(config('services.xai.key'))
            ->post('https://api.x.ai/v1/chat/completions', [
                'model' => config('services.xai.model'),
                'messages' => $messages,
                'max_tokens' => 500,
            ]);

        if ($response->failed()) {
            return response()->json(
                ['message' => "Sorry, I'm having trouble connecting right now. Please try again."],
                500
            );
        }

        return response()->json([
            'message' => $response->json('choices.0.message.content'),
        ]);
    }
}
