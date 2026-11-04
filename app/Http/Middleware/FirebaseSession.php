<?php

namespace App\Http\Middleware;

use App\Exceptions\AccountException;
use App\Services\FirebaseAccounts;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FirebaseSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = $request->session()->get('firebase_user');
        if (! $sessionUser) {
            return redirect()->guest(route('login'));
        }
        try {
            if (! isset($sessionUser['credential_version'], $sessionUser['authenticated_at']) || now()->timestamp - $sessionUser['authenticated_at'] > config('firebase.session_max_age')) {
                throw new AccountException('Your session has expired. Please sign in again.', status: 401);
            }
            $user = app(FirebaseAccounts::class)->identity($sessionUser['uid']);
            if (! hash_equals($sessionUser['credential_version'], $user['credential_version'])) {
                throw new AccountException('Your account credentials have changed. Please sign in again.', status: 401);
            }
        } catch (AccountException $exception) {
            if ($exception->status === 503) {
                return response()->view('errors.service-unavailable', [], 503)->header('Retry-After', '30');
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['account' => $exception->getMessage()]);
        }
        $user['authenticated_at'] = $sessionUser['authenticated_at'];
        $request->session()->put('firebase_user', $user);
        $identity = new GenericUser(array_merge($user, ['id' => $user['uid']]));
        $request->attributes->set('firebase_identity', $identity);
        Auth::guard('web')->setUser($identity);

        return $next($request);
    }
}
