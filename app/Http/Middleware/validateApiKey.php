<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

class validateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        dd('hello');
        $plainKey = $request->header('X-API-KEY');
        $api_application_key = config('services.odrs_api_application_key.key');
        
        if (!$plainKey || $plainKey !== $api_application_key) {
            return response()->json(['message' => 'Unauthorized'], 402);
        }

        // hash the incoming key then compare
        $apiKey = ApiKey::where('key', hash('sha256', $plainKey))
                        ->where('is_active', true)
                        ->first();

        if (!$apiKey) {
            return response()->json(['error' => 'Invalid or inactive API key'], 403);
        }

        return $next($request);
    }
}
