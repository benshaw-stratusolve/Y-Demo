import { describe, expect, it } from 'vitest';
import { charCounterClass, showCharCounter } from './char-counter';

describe('showCharCounter', () => {
    it('shows when exactly at the threshold (50 left)', () => {
        expect(showCharCounter(50)).toBe(true);
    });

    it('shows when within the threshold (20 left)', () => {
        expect(showCharCounter(20)).toBe(true);
    });

    it('shows when over the limit (negative)', () => {
        expect(showCharCounter(-1)).toBe(true);
    });

    it('hides when one above the threshold (51 left)', () => {
        expect(showCharCounter(51)).toBe(false);
    });

    it('hides when well below threshold (280 left)', () => {
        expect(showCharCounter(280)).toBe(false);
    });
});

describe('charCounterClass', () => {
    it('returns red when over the limit', () => {
        expect(charCounterClass(-1)).toBe('text-red-500');
        expect(charCounterClass(-10)).toBe('text-red-500');
    });

    it('returns orange when 20 or fewer left', () => {
        expect(charCounterClass(20)).toBe('text-orange-500');
        expect(charCounterClass(1)).toBe('text-orange-500');
        expect(charCounterClass(0)).toBe('text-orange-500');
    });

    it('returns yellow when between 21 and 50 left', () => {
        expect(charCounterClass(21)).toBe('text-yellow-500');
        expect(charCounterClass(50)).toBe('text-yellow-500');
    });
});
