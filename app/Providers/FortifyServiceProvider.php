<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if (auth()->check()) {
                return redirect()->route('dashboard');
            }

            return Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister' => Features::enabled(Features::registration()),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            $token = $request->route('token');
            $email = $request->query('email', $request->email);

            $user = User::where('email', $email)->first();
            $tokenValid = $user && \Illuminate\Support\Facades\Password::broker()->tokenExists($user, $token);

            // Distinguish "already used" (record deleted after reset) from "expired" (record still exists but too old)
            $tokenInvalidReason = null;
            if (! $tokenValid) {
                $recordExists = DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                    ->where('email', $email)
                    ->exists();
                $tokenInvalidReason = $recordExists ? 'expired' : 'used';
            }

            return Inertia::render('auth/ResetPassword', [
                'email' => $email,
                'token' => $token,
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
                'tokenInvalid' => ! $tokenValid,
                'tokenInvalidReason' => $tokenInvalidReason,
            ]);
        });

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function () {
            if (auth()->check()) {
                return redirect()->route('dashboard');
            }

            return Inertia::render('auth/Register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        });

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        // Progressive lockout is handled by ProgressiveLoginThrottle middleware.
        // This limiter is set high to avoid conflicting with our custom logic.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perHour(10000)->by($request->ip());
        });
    }
}
