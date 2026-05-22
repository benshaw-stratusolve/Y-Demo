<?php

namespace App\Concerns;

trait HasAvatarFallback
{
    public static function avatarFallbackUrl(string $name): string
    {
        $colors = ['ef4444', 'f97316', 'f59e0b', '22c55e', '14b8a6', '06b6d4', '3b82f6', '6366f1', '8b5cf6', 'a855f7', 'ec4899', 'f43f5e'];
        $sum = array_sum(array_map('ord', str_split($name)));
        $color = $colors[$sum % count($colors)];

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=ffffff&background='.$color;
    }
}
