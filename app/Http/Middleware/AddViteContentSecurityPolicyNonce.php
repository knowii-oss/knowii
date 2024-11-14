<?php

namespace App\Http\Middleware;

use Bepsvpt\SecureHeaders\SecureHeaders;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Vite scripts include a nonce for Content Security Policy.
 * Reference: https://laravel.com/docs/11.x/vite#content-security-policy-csp-nonce
 */
class AddViteContentSecurityPolicyNonce
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     *
     * @throws \Exception
     */
    final public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce(SecureHeaders::nonce('script'));

        return $next($request);
    }
}
