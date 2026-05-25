import { echo } from '@/lib/echo';

type Post = Record<string, any>;
type Notification = {
    id: string;
    type: string;
    data: Record<string, any>;
    read: boolean;
    created_at: string;
    is_following_actor: boolean;
};

let newPosts = $state<Post[]>([]);
let postCounts = $state<Record<number, { likes_count: number; replies_count: number }>>({});
let liveUnreadIncrement = $state(0);
let incomingNotifications = $state<Notification[]>([]);

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

export const realtimeStore = {
    get newPosts() { return newPosts; },
    get postCounts() { return postCounts; },
    get liveUnreadIncrement() { return liveUnreadIncrement; },
    get incomingNotifications() { return incomingNotifications; },
    subscribeToUser,
    unsubscribeFromUser,
    consumeNewPosts,
    consumeIncomingNotifications,
    resetUnreadIncrement,
};
