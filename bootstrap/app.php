<?php

use App\Exceptions\AccountException;
use App\Http\Middleware\FirebaseSession;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RequireAccountSetup;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        // Vercel terminates HTTPS at its proxy; local installations trust none by default.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        // User-based throttles must run after the current Firebase identity is loaded.
        $middleware->prependToPriorityList(ThrottleRequests::class, FirebaseSession::class);
        $middleware->alias([
            'no.cache' => PreventBackHistory::class,
            'firebase.session' => FirebaseSession::class,
            'role' => RequireRole::class,
            'account.ready' => RequireAccountSetup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'new_password', 'new_password_confirmation', 'token']);
        $exceptions->dontReport([AccountException::class]);
        $exceptions->render(function (AccountException $exception, Request $request) {
            if ($exception->status === 401 && $request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                if (! $request->expectsJson()) {
                    return redirect()->route('login')->withErrors(['account' => $exception->getMessage()]);
                }
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'errors' => [$exception->field => [$exception->getMessage()]]], $exception->status);
            }
            if ($request->isMethod('GET')) {
                return response()->view('errors.service-unavailable', [], 503)->header('Retry-After', '30');
            }

            return redirect()->back()->withErrors([$exception->field => $exception->getMessage()])->withInput($request->only('name', 'email', 'role', 'block_id'));
        });
    })->create();
