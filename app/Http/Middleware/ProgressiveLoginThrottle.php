<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProgressiveLoginThrottle
{
    private const MAX_ATTEMPTS = 5;

    // Successive lockout durations in minutes per escalation level
    private const LOCKOUT_MINUTES = [1, 10, 30, 60];

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->isMethod('POST') || ! $request->is('login', 'admin/login')) {
            return $next($request);
        }

        $key = $this->throttleKey($request);

        if (Cache::has("lk:{$key}")) {
            return $this->lockedOutResponse($request, $key);
        }

        $response = $next($request);

        if (auth()->check()) {
            $this->clearAttempts($key);
        } elseif ($request->filled(config('fortify.username', 'email'))) {
            $this->recordFailure($key);
        }

        return $response;
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower(trim((string) $request->input(config('fortify.username', 'email'))))
            .'|'.$request->ip();
    }

    private function lockedOutResponse(Request $request, string $key): RedirectResponse
    {
        $unlockAt = (int) Cache::get("lk:{$key}");
        $seconds = max(1, $unlockAt - now()->timestamp);
        $minutes = (int) ceil($seconds / 60);
        $label = $minutes === 1 ? '1 minute' : "{$minutes} minutes";

        return back()
            ->withInput($request->only(config('fortify.username', 'email')))
            ->withErrors([
                config('fortify.username', 'email') => "Too many failed attempts. Try again in {$label}.",
            ]);
    }

    private function recordFailure(string $key): void
    {
        $attemptsKey = "la:{$key}";
        $levelKey = "llv:{$key}";

        if (! Cache::has($attemptsKey)) {
            Cache::put($attemptsKey, 0, now()->addDay());
        }

        $attempts = (int) Cache::increment($attemptsKey);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $level = (int) Cache::get($levelKey, 0);
            $minutes = self::LOCKOUT_MINUTES[min($level, count(self::LOCKOUT_MINUTES) - 1)];
            $expiry = now()->addMinutes($minutes);

            Cache::put("lk:{$key}", $expiry->timestamp, $expiry);
            Cache::put($levelKey, $level + 1, now()->addDays(7));
            Cache::forget($attemptsKey);
        }
    }

    private function clearAttempts(string $key): void
    {
        Cache::forget("la:{$key}");
        Cache::forget("lk:{$key}");
        Cache::forget("llv:{$key}");
    }
}
