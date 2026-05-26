export function showCharCounter(charsLeft: number): boolean {
    return charsLeft <= 50;
}

export function charCounterClass(charsLeft: number): string {
    if (charsLeft < 0) return 'text-red-500';
    if (charsLeft <= 20) return 'text-orange-500';
    return 'text-yellow-500';
}
