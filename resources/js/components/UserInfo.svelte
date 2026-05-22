<script lang="ts">
    import UserAvatar from '@/components/UserAvatar.svelte';
    import type { User } from '@/types';

    let {
        user,
        showEmail = false,
    }: {
        user: User;
        showEmail?: boolean;
    } = $props();
</script>

<UserAvatar user={user} size="xs" class="rounded-lg" fallbackClass="rounded-lg" />

<div class="grid flex-1 text-left text-sm leading-tight">
    <span class="truncate font-medium">{user.name}</span>
    {#if showEmail}
        <span class="truncate text-xs text-muted-foreground">{user.email}</span>
    {/if}
</div>
