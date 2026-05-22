<?php

use App\Services\ProfanityService;

beforeEach(function () {
    $this->service = new ProfanityService;
});

// ─── Basic detection ──────────────────────────────────────────────────────────

it('detects plain profanity', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'fuck', 'shit', 'ass', 'bitch', 'cunt', 'dick', 'cock',
    'bullshit', 'motherfucker', 'asshole', 'dumbass',
]);

it('allows clean text', function (string $text) {
    expect($this->service->contains($text))->toBeFalse();
})->with([
    'Hello world',
    'This is a great post!',
    'I love this app',
    'passionate',
    'assume',
]);

// ─── Bypass: diacritics and combining characters ──────────────────────────────

it('detects profanity with diacritics', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'fück',
    'shït',
    'āss',
]);

// ─── Bypass: zero-width and invisible characters ──────────────────────────────

it('detects profanity with zero-width spaces inserted', function () {
    expect($this->service->contains("f\u{200B}u\u{200B}c\u{200B}k"))->toBeTrue();
    expect($this->service->contains("s\u{200B}h\u{200B}i\u{200B}t"))->toBeTrue();
});

it('detects profanity with zero-width non-joiners', function () {
    expect($this->service->contains("f\u{200C}u\u{200C}c\u{200C}k"))->toBeTrue();
});

it('detects profanity with soft hyphens', function () {
    expect($this->service->contains("f\u{00AD}u\u{00AD}c\u{00AD}k"))->toBeTrue();
});

// ─── Bypass: Unicode homoglyphs ───────────────────────────────────────────────

it('detects profanity using Cyrillic look-alikes', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'аss',       // Cyrillic а (U+0430) replacing Latin a
    'fuсk',      // Cyrillic с (U+0441) replacing c
    'diсk',      // Cyrillic с replacing c
]);

it('detects profanity using Greek look-alikes', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'fυck',      // Greek υ (U+03C5) replacing u
    'αss',       // Greek α (U+03B1) replacing a
]);

// ─── Bypass: leet speak ───────────────────────────────────────────────────────

it('detects leet-speak substitutions', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'sh1t',     // 1 → i
    '@ss',      // @ → a
    '$hit',     // $ → s
    'a$$',      // $ → s (double)
    'r3tard',   // 3 → e
    'b1tch',    // 1 → i
]);

// ─── Bypass: spaced / separated letters ──────────────────────────────────────

it('detects profanity with spaces between letters', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'f u c k',
    's h i t',
    'a s s',
]);

it('detects profanity with dots between letters', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'f.u.c.k',
    's.h.i.t',
    'a.s.s',
]);

it('detects profanity with dashes between letters', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'f-u-c-k',
    's-h-i-t',
]);

it('detects profanity with underscores between letters', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'f_u_c_k',
    's_h_i_t',
]);

// ─── Bypass: vowel replaced by symbol ────────────────────────────────────────

it('detects profanity when vowels are replaced by symbols', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'f*ck',
    'f#ck',
    'f(ck',
    'sh*t',
    'sh#t',
    '*ss',
    'b*tch',
    'c*nt',
    'd*ck',
]);

// ─── Bypass: combinations ─────────────────────────────────────────────────────

it('detects profanity with mixed bypass techniques', function (string $text) {
    expect($this->service->contains($text))->toBeTrue();
})->with([
    'f * c k',
    'f.*.c.k',
]);

it('detects profanity with zero-width plus symbol vowel', function () {
    expect($this->service->contains("f\u{200B}*\u{200B}ck"))->toBeTrue();
});

// ─── False positive protection ────────────────────────────────────────────────

it('does not flag words that merely contain profanity as a substring', function (string $text) {
    expect($this->service->contains($text))->toBeFalse();
})->with([
    'bass',
    'class',
    'grass',
    'passage',
    'harass',
    'assume',
    'cocktail',
    'dicker',
    'scrap',
    'passionate',
]);
