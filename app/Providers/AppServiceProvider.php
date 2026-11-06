<?php

namespace App\Providers;

use App\Exceptions\AccountException;
use App\Services\FirebaseServiceAccount;
use App\Services\FirestoreCredentials;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as LaravelAuth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(FirestoreCredentials::class);
        $this->app->singleton(Factory::class, function () {
            try {
                if (! config('firebase.database_url')) {
                    throw new AccountException('The account service is not configured. Contact an administrator.', status: 503);
                }

                return (new Factory)->withServiceAccount($this->app->make(FirebaseServiceAccount::class)->source())
                    ->withDatabaseUri(config('firebase.database_url'))
                    ->withHttpClientOptions(HttpClientOptions::default()->withConnectTimeout(5)->withTimeout(15));
            } catch (Throwable) {
                throw new AccountException('The account service is not configured. Contact an administrator.', status: 503);
            }
        });
        $this->app->singleton(Auth::class, fn ($app) => $app->make(Factory::class)->createAuth());
        $this->app->singleton(Database::class, fn ($app) => $app->make(Factory::class)->createDatabase());
    }

    public function boot(): void
    {
        LaravelAuth::viaRequest('firebase', fn (Request $request) => $request->attributes->get('firebase_identity'));
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login:'.hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())),
        ]);
        RateLimiter::for('activation', fn (Request $request) => [
            Limit::perMinute(30)->by('activation-ip:'.$request->ip())->response($this->activationLimitResponse(...)),
            Limit::perMinute(1)->by('activation-email:'.hash('sha256', strtolower(trim((string) $request->input('email')))))->response($this->activationLimitResponse(...)),
            Limit::perHour(5)->by('activation-hour:'.hash('sha256', strtolower(trim((string) $request->input('email')))))->response($this->activationLimitResponse(...)),
        ]);
        // Opening an emailed link and setting a password must not consume the resend quota.
        RateLimiter::for('activation-link', fn (Request $request) => [
            Limit::perMinute(60)->by('activation-link:'.$request->ip())->response($this->activationLimitResponse(...)),
        ]);
        RateLimiter::for('activation-password', fn (Request $request) => [
            Limit::perMinute(60)->by('activation-password-ip:'.$request->ip())->response($this->activationLimitResponse(...)),
            Limit::perMinute(5)->by('activation-password:'.hash('sha256', (string) $request->session()->get('student_activation.email', $request->ip())))->response($this->activationLimitResponse(...)),
        ]);
        RateLimiter::for('recovery', fn (Request $request) => [
            Limit::perMinute(5)->by('recovery:'.$request->ip()),
            Limit::perHour(3)->by('recovery-email:'.hash('sha256', strtolower((string) $request->input('email')))),
        ]);
        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(5)->by('account:'.$request->user()?->getAuthIdentifier()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(1)->by('verify:'.$request->user()?->getAuthIdentifier()));
    }

    private function activationLimitResponse(Request $request, array $headers)
    {
        $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
        $wait = $seconds < 60 ? $seconds.' seconds' : (int) ceil($seconds / 60).' minute(s)';
        $message = $request->routeIs('new-user.send')
            ? 'Please wait '.$wait.' before requesting another verification email. If you already received one, open the latest email, verify your address, and select Continue to set your password.'
            : 'Please wait '.$wait.' before trying again. If you opened a verification link, reopen the original email link after waiting.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429, $headers);
        }

        return redirect()->route($request->routeIs('new-user.complete') ? 'new-user.password' : 'new-user')
            ->withErrors(['activation' => $message])->withInput($request->only('email'))->withHeaders($headers);
    }
}
