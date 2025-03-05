<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $shop = $request->get('shop');
        $policy = "frame-ancestors https://{$shop} https://admin.shopify.com";
        $response->headers->set('Content-Security-Policy', $policy);
        return $response;
    }
}
