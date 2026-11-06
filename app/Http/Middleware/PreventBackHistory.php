<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        $response->headers->set(
            'Cache-Control',
            'no-cache, no-store, max-age=0, must-revalidate, private'
        );

        $response->headers->set(
            'Pragma',
            'no-cache'
        );

        $response->headers->set(
            'Expires',
            'Sat, 01 Jan 1990 00:00:00 GMT'
        );

        return $response;
    }
}
