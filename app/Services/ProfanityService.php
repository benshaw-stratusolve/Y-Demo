<?php

namespace App\Services;

use Normalizer;

class ProfanityService
{
    private array $words = [
        'fuck', 'fucker', 'fucking', 'fucked', 'fuckhead', 'motherfucker', 'clusterfuck',
        'shit', 'shithead', 'shitface', 'bullshit', 'dipshit', 'horseshit',
        'bitch', 'bitchy', 'son of a bitch',
        'ass', 'asshole', 'dumbass', 'jackass', 'smartass', 'badass', 'fatass',
        'arse', 'arsehole',
        'cunt', 'dick', 'dickhead', 'cock', 'cockhead',
        'pussy', 'twat', 'knob', 'bellend', 'wanker', 'tosser', 'prick',
        'bastard', 'faggot', 'fag', 'whore', 'slut', 'skank', 'hoe',
        'retard', 'retarded', 'spastic',
        'piss', 'pissed', 'pisser',
        'crap', 'poes', 'moron', 'idiot', 'imbecile', 'numbnuts',
        'nigger', 'nigga', 'chink', 'spic', 'kike', 'wetback', 'cracker',
        'tranny', 'dyke',
    ];

    public function contains(string $text): bool
    {
        $normalized = $this->normalize($text);

        // Extract word-like tokens (letters, digits, symbol wildcards)
        preg_match_all('/[a-z0-9*#()]+/i', $normalized, $matches);

        foreach ($matches[0] as $token) {
            $token = strtolower($token);
            // Replace symbol wildcards with control-char placeholders before preg_quote escapes them
            // (preg_quote escapes NUL but not \x01-\x04), then restore as regex any-char (.) after quoting.
            $tmp = strtr($token, ['*' => "\x01", '#' => "\x02", '(' => "\x03", ')' => "\x04"]);
            $quoted = preg_quote($tmp, '/');
            $pattern = '/^'.strtr($quoted, ["\x01" => '.', "\x02" => '.', "\x03" => '.', "\x04" => '.']).'$/i';
            foreach ($this->words as $word) {
                if (str_contains($word, ' ')) {
                    continue;
                }
                if (preg_match($pattern, $word)) {
                    return true;
                }
            }
        }

        // Multi-word phrase check (direct match on normalized text)
        foreach ($this->words as $word) {
            if (str_contains($word, ' ') && preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/i', $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        // Strip zero-width and invisible characters
        $text = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{00AD}\x{FEFF}]/u', '', $text);

        // Map Unicode homoglyphs (Cyrillic / Greek look-alikes) to Latin equivalents
        $text = strtr($text, $this->homoglyphs());

        // NFD + strip combining marks removes diacritics (ü → u, ā → a, etc.)
        $nfd = normalizer_normalize($text, Normalizer::FORM_D);
        if ($nfd !== false) {
            $text = preg_replace('/\p{Mn}/u', '', $nfd);
        }

        // Leet-speak / common symbol substitutions, then lowercase
        $text = strtr(strtolower($text), [
            '1' => 'i',
            '3' => 'e',
            '0' => 'o',
            '@' => 'a',
            '$' => 's',
        ]);

        // Collapse separator-split single characters: "f u c k" → "fuck", "f.*.c.k" → "f*ck"
        // Matches: single char, then (separator(s) + single char) at least twice
        $text = preg_replace_callback(
            '/(?<![a-z0-9*#(])([a-z0-9*#(])([\s._\-]+[a-z0-9*#(]){2,}(?![a-z0-9*#(])/iu',
            fn ($m) => preg_replace('/[\s._\-]+/u', '', $m[0]),
            $text
        );

        return $text;
    }

    /** @return array<string, string> */
    private function homoglyphs(): array
    {
        return [
            // Cyrillic look-alikes
            'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'r',
            'с' => 'c', 'у' => 'u', 'х' => 'x',
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'М' => 'M',
            'Н' => 'H', 'О' => 'O', 'Р' => 'R', 'С' => 'C',
            'Т' => 'T', 'У' => 'Y', 'Х' => 'X',
            // Greek look-alikes
            'α' => 'a', 'β' => 'b', 'ε' => 'e', 'ι' => 'i',
            'κ' => 'k', 'ν' => 'n', 'ο' => 'o', 'ρ' => 'r',
            'τ' => 't', 'υ' => 'u', 'χ' => 'x',
            'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ι' => 'I',
            'Κ' => 'K', 'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'R',
            'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
        ];
    }
}
