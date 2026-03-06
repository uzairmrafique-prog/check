<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAppKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Validate the app key from request
        if ($request->appKey !== env('APP_KEY_MOBILE')) {
            return response()->json([
                'auth' => false,
                'msg'  => 'Invalid application key.',
            ], 401);
        }

        return $next($request);
    }
}
