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
use Illuminate\Validation\Rules\File;
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

        $clearedAt = $conversation->clearedAtFor($user->id);

        $messages = $conversation->messages()
            ->with(['sender', 'reactions'])
            ->when($clearedAt, fn ($q) => $q->where('created_at', '>', $clearedAt))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message) => $this->messagePayload($message, $user->id));

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
                'is_muted' => $user->hasMuted($otherUser->id),
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

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1000', 'required_without:image'],
            'image' => ['nullable', File::image()->max(5 * 1024)],
        ]);

        $recipientId = $conversation->user1_id === $user->id
            ? $conversation->user2_id
            : $conversation->user1_id;

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('message-images', 'public')
            : null;

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? '',
            'image_path' => $imagePath,
        ]);

        // Restore conversation visibility for both parties when a new message is sent.
        $conversation->update(['deleted_by_user1' => false, 'deleted_by_user2' => false]);

        $recipient = User::find($recipientId);
        $silenced = $recipient->hasMuted($user->id);

        try {
            broadcast(new MessageSent($message, $recipientId, $silenced));
        } catch (\Throwable) {
            // Reverb unavailable — message saved, broadcast skipped
        }

        return to_route('messages.show', $conversation);
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

        $recipient = User::find($recipientId);
        if (! $recipient->hasMuted($user->id)) {
            try {
                broadcast(new UserTyping($conversation->id, $user->id, $recipientId));
            } catch (\Throwable) {
                // Reverb unavailable
            }
        }

        return response()->json(['ok' => true]);
    }

    public function clearChat(Conversation $conversation): RedirectResponse
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $conversation->clearFor($user->id);

        return to_route('messages.show', $conversation);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $userId = $user->id;

        if ($conversation->user1_id === $userId) {
            $conversation->update(['deleted_by_user1' => true]);
        } else {
            $conversation->update(['deleted_by_user2' => true]);
        }

        return redirect()->route('messages.index');
    }

    /** @return array<int, array<string, mixed>> */
    private function followingUsers(): array
    {
        return auth()->user()
            ->followedUsers()
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
            ->where(function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('user1_id', $user->id)->where('deleted_by_user1', false);
                })->orWhere(function ($inner) use ($user) {
                    $inner->where('user2_id', $user->id)->where('deleted_by_user2', false);
                });
            })
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->get()
            ->map(function (Conversation $conv) use ($user) {
                $other = $conv->otherUser($user->id);
                $clearedAt = $conv->clearedAtFor($user->id);
                $latestMsg = $conv->latestMessage;

                // Hide latest message preview if it was sent before the user cleared the chat.
                if ($clearedAt && $latestMsg && $latestMsg->created_at?->lte($clearedAt)) {
                    $latestMsg = null;
                }

                return [
                    'id' => $conv->id,
                    'other_user' => [
                        'id' => $other->id,
                        'name' => $other->name,
                        'username' => $other->username,
                        'avatar_url' => $other->avatar_url,
                    ],
                    'latest_message' => $latestMsg ? ($latestMsg->body !== '' ? $latestMsg->body : 'Photo') : null,
                    'latest_message_at' => $latestMsg?->created_at?->diffForHumans(),
                    'unread_count' => $conv->unread_count,
                ];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function messagePayload(Message $message, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'image_url' => $message->image_url,
            'sender_id' => $message->sender_id,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
            ],
            'read_at' => $message->read_at,
            'created_at' => $message->created_at->diffForHumans(),
            'is_mine' => $message->sender_id === $viewerId,
            'reactions' => $message->reactions
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'reacted' => $group->contains('user_id', $viewerId),
                ])
                ->values(),
        ];
    }
}
