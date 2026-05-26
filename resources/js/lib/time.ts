export function timeAgo(dateStr: string, now: Date = new Date()): string {
    const date = new Date(dateStr);
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
    if (seconds < 7 * 86400) return `${Math.floor(seconds / 86400)}d`;

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
