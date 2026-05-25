export type Notification = {
    id: string;
    type: string;
    data: Record<string, any>;
    read: boolean;
    created_at: string;
    is_following_actor: boolean;
};
