import { echo } from '@/lib/echo';
import { notifications } from '@/lib/notifications.svelte';

type Post = Record<string, any>;
type Notification = {
    id: string;
    type: string;
    data: Record<string, any>;
    read: boolean;
    created_at: string;
    is_following_actor: boolean;
};
type MessageType = {
    id: number;
    conversation_id: number;
    body: string;
    image_url: string | null;
    sender_id: number;
    sender: { id: number; name: string; username: string; avatar_url: string | null };
    created_at: string;
    is_mine: boolean;
};

let newPosts = $state<Post[]>([]);
let postCounts = $state<Record<number, { likes_count: number; replies_count: number }>>({});
let liveUnreadIncrement = $state(0);
let incomingNotifications = $state<Notification[]>([]);
let deletedPostIds = $state<Set<number>>(new Set());
let newMessages = $state<Record<number, MessageType[]>>({});
let typingConversations = $state<Set<number>>(new Set());
let unreadMessagesIncrement = $state(0);

const typingTimeouts = new Map<number, ReturnType<typeof setTimeout>>();

let activeChannel: ReturnType<typeof echo.private> | null = null;
let activeUserId: number | null = null;

function subscribeToUser(userId: number): void {
    if (activeChannel) return;

    activeUserId = userId;
    activeChannel = echo.private(`user.${userId}`)
        .listen('.PostBroadcast', (e: { post: Post }) => {
            newPosts = [e.post, ...newPosts];
        })
        .listen('.PostInteractionUpdated', (e: { post_id: number; likes_count: number; replies_count: number }) => {
            postCounts = { ...postCounts, [e.post_id]: { likes_count: e.likes_count, replies_count: e.replies_count } };
        })
        .listen('.NotificationSent', (e: Notification) => {
            incomingNotifications = [e, ...incomingNotifications];
            liveUnreadIncrement += 1;
        })
        .listen('.PostDeletedBroadcast', (e: { post_id: number }) => {
            deletedPostIds = new Set([...deletedPostIds, e.post_id]);
        })
        .listen('.MessageSent', (e: { message: MessageType & { silenced?: boolean } }) => {
            const convId = e.message.conversation_id;
            newMessages = {
                ...newMessages,
                [convId]: [...(newMessages[convId] ?? []), e.message],
            };
            unreadMessagesIncrement += 1;
            if (!e.message.silenced) {
                const fallback = e.message.image_url ? 'Photo' : '';
                const messagePreview = e.message.body || fallback;
                const preview = messagePreview.length > 60 ? messagePreview.slice(0, 60) + '...' : messagePreview;
                notifications.add({ type: 'message', title: e.message.sender.name, description: preview });
            }
        })
        .listen('.UserTyping', (e: { conversation_id: number }) => {
            const convId = e.conversation_id;
            typingConversations = new Set([...typingConversations, convId]);
            if (typingTimeouts.has(convId)) {
                clearTimeout(typingTimeouts.get(convId)!);
            }
            typingTimeouts.set(convId, setTimeout(() => {
                typingConversations = new Set(
                    [...typingConversations].filter((id) => id !== convId)
                );
                typingTimeouts.delete(convId);
            }, 3000));
        });
}

function unsubscribeFromUser(): void {
    if (activeUserId !== null) {
        echo.leave(`user.${activeUserId}`);
        activeChannel = null;
        activeUserId = null;
    }
    newPosts = [];
    postCounts = {};
    liveUnreadIncrement = 0;
    incomingNotifications = [];
    deletedPostIds = new Set();
    newMessages = {};
    typingConversations = new Set();
    typingTimeouts.forEach((t) => clearTimeout(t));
    typingTimeouts.clear();
    unreadMessagesIncrement = 0;
}

function consumeNewPosts(): Post[] {
    const posts = [...newPosts];
    newPosts = [];
    return posts;
}

function consumeIncomingNotifications(): Notification[] {
    const notifications = [...incomingNotifications];
    incomingNotifications = [];
    return notifications;
}

function resetUnreadIncrement(): void {
    liveUnreadIncrement = 0;
}

function consumeNewMessages(conversationId: number): MessageType[] {
    const msgs = [...(newMessages[conversationId] ?? [])];
    const { [conversationId]: _, ...rest } = newMessages;
    newMessages = rest;
    return msgs;
}

function resetUnreadMessagesIncrement(): void {
    unreadMessagesIncrement = 0;
}

function consumeDeletedPostIds(): Set<number> {
    const ids = new Set(deletedPostIds);
    deletedPostIds = new Set();
    return ids;
}

export interface RealtimeStore {
    readonly newPosts: Post[];
    readonly postCounts: Record<number, { likes_count: number; replies_count: number }>;
    readonly liveUnreadIncrement: number;
    readonly incomingNotifications: Notification[];
    readonly deletedPostIds: Set<number>;
    readonly newMessages: Record<number, MessageType[]>;
    readonly typingConversations: Set<number>;
    readonly unreadMessagesIncrement: number;
    subscribeToUser(userId: number): void;
    unsubscribeFromUser(): void;
    consumeNewPosts(): Post[];
    consumeIncomingNotifications(): Notification[];
    resetUnreadIncrement(): void;
    consumeNewMessages(conversationId: number): MessageType[];
    resetUnreadMessagesIncrement(): void;
    consumeDeletedPostIds(): Set<number>;
}

export const realtimeStore: RealtimeStore = {
    get newPosts() { return newPosts; },
    get postCounts() { return postCounts; },
    get liveUnreadIncrement() { return liveUnreadIncrement; },
    get incomingNotifications() { return incomingNotifications; },
    get deletedPostIds() { return deletedPostIds; },
    get newMessages() { return newMessages; },
    get typingConversations() { return typingConversations; },
    get unreadMessagesIncrement() { return unreadMessagesIncrement; },
    subscribeToUser,
    unsubscribeFromUser,
    consumeNewPosts,
    consumeIncomingNotifications,
    resetUnreadIncrement,
    consumeNewMessages,
    resetUnreadMessagesIncrement,
    consumeDeletedPostIds,
};
