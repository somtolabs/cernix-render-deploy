<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NgrokHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Suppress ngrok's free-tier browser-warning interstitial page.
        // The interstitial breaks first-load in browsers and blocks all mobile
        // access via the public URL. This header disables it globally.
        $response->headers->set('ngrok-skip-browser-warning', '1');
        return $response;
    }
}
