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
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));
        RateLimiter::for('recovery', fn (Request $request) => [
            Limit::perMinute(5)->by('recovery:'.$request->ip()),
            Limit::perHour(3)->by('recovery-email:'.hash('sha256', strtolower((string) $request->input('email')))),
        ]);
        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(5)->by('account:'.$request->user()?->getAuthIdentifier()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(1)->by('verify:'.$request->user()?->getAuthIdentifier()));
    }
}
