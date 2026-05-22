<?php

namespace App\Services;

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
        foreach ($this->words as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $text)) {
                return true;
            }
        }

        return false;
    }
}
