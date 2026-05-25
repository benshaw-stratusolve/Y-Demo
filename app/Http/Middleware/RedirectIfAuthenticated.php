<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated extends BaseMiddleware
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check() && $request->routeIs('password.reset')) {
                return $this->handleAuthenticatedResetAttempt($request);
            }

            if (Auth::guard($guard)->check()) {
                return redirect($this->redirectTo($request));
            }
        }

        return $next($request);
    }

    private function handleAuthenticatedResetAttempt(Request $request): Response
    {
        $user = Auth::user();
        $token = $request->route('token');
        $email = $request->query('email', $request->input('email'));

        $tokenValid = $user && Password::broker()->tokenExists($user, $token);

        if (! $tokenValid) {
            $recordExists = DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $email ?: $user->email)
                ->exists();

            $reason = $recordExists ? 'expired' : 'used';

            Inertia::flash('toast', [
                'type' => $reason === 'used' ? 'info' : 'warning',
                'title' => $reason === 'used' ? 'Link already used' : 'Link expired',
                'description' => $reason === 'used'
                    ? 'Your password was already reset using this link. You are already logged in.'
                    : 'This password reset link has expired. You are already logged in.',
            ]);
        }

        return redirect()->route('dashboard');
    }
}
