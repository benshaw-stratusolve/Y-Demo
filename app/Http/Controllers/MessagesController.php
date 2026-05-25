<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Messages', [
            'conversations' => $this->conversationsList(),
            'activeConversation' => null,
            'messages' => null,
            'followingUsers' => $this->followingUsers(),
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $otherUser = $conversation->otherUser($user->id);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $msg) => [
                'id' => $msg->id,
                'conversation_id' => $msg->conversation_id,
                'body' => $msg->body,
                'sender_id' => $msg->sender_id,
                'sender' => [
                    'id' => $msg->sender->id,
                    'name' => $msg->sender->name,
                    'username' => $msg->sender->username,
                    'avatar_url' => $msg->sender->avatar_url,
                ],
                'read_at' => $msg->read_at,
                'created_at' => $msg->created_at->diffForHumans(),
                'is_mine' => $msg->sender_id === $user->id,
            ]);

        return Inertia::render('Messages', [
            'conversations' => $this->conversationsList(),
            'activeConversation' => [
                'id' => $conversation->id,
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'username' => $otherUser->username,
                    'avatar_url' => $otherUser->avatar_url,
                ],
            ],
            'messages' => $messages,
            'followingUsers' => $this->followingUsers(),
        ]);
    }

    public function findOrCreate(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422);

        $conversation = Conversation::findOrCreateBetween(auth()->id(), $user->id);

        return to_route('messages.show', $conversation);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $request->validate(['body' => 'required|string|max:1000']);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $request->body,
        ]);

        $recipientId = $conversation->user1_id === $user->id
            ? $conversation->user2_id
            : $conversation->user1_id;

        try {
            broadcast(new MessageSent($message, $recipientId));
        } catch (\Throwable) {
            // Reverb unavailable — message saved, broadcast skipped
        }

        return back();
    }

    public function typing(Conversation $conversation): JsonResponse
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $recipientId = $conversation->user1_id === $user->id
            ? $conversation->user2_id
            : $conversation->user1_id;

        try {
            broadcast(new UserTyping($conversation->id, $user->id, $recipientId));
        } catch (\Throwable) {
            // Reverb unavailable
        }

        return response()->json(['ok' => true]);
    }

    /** @return array<int, array<string, mixed>> */
    private function followingUsers(): array
    {
        return auth()->user()
            ->followingUsers()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar_url')
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function conversationsList(): array
    {
        $user = auth()->user();

        return Conversation::with(['user1', 'user2', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ])
            ->where('user1_id', $user->id)
            ->orWhere('user2_id', $user->id)
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->get()
            ->map(function (Conversation $conv) use ($user) {
                $other = $conv->otherUser($user->id);

                return [
                    'id' => $conv->id,
                    'other_user' => [
                        'id' => $other->id,
                        'name' => $other->name,
                        'username' => $other->username,
                        'avatar_url' => $other->avatar_url,
                    ],
                    'latest_message' => $conv->latestMessage?->body,
                    'latest_message_at' => $conv->latestMessage?->created_at?->diffForHumans(),
                    'unread_count' => $conv->unread_count,
                ];
            })
            ->all();
    }
}
