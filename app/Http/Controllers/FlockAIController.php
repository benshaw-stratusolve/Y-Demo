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

        $contents = collect($request->input('history', []))
            ->map(fn (array $msg) => [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ])
            ->push([
                'role' => 'user',
                'parts' => [['text' => $request->input('message')]],
            ])
            ->values()
            ->all();

        $model = config('services.gemini.model');
        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".config('services.gemini.key'),
            [
                'system_instruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
                'contents' => $contents,
            ]
        );

        if ($response->failed()) {
            return response()->json(
                ['message' => "Sorry, I'm having trouble connecting right now. Please try again."],
                500
            );
        }

        return response()->json([
            'message' => $response->json('candidates.0.content.parts.0.text'),
        ]);
    }
}
