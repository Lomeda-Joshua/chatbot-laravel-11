<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SsoSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check SSO sessions for authenticated users who logged in via SSO
        if (Auth::check() && Session::get('sso_authenticated')) {
            $lastCheck = Session::get('last_sso_check', 0);
            $checkInterval = config('sso.session.check_interval', 300); // 5 minutes
            
            // Check if we need to verify with Project 1 (every 5 minutes)
            if ($lastCheck < (time() - $checkInterval)) {
                $ssoToken = Session::get('sso_token');
                
                if ($ssoToken) {
                    try {
                        $response = Http::timeout(5)->post(config('sso.project1.url') . '/api/sso/verify-token', [
                            'token' => $ssoToken
                        ]);
                        
                        if (!$response->successful()) {
                            Log::info('SSO token expired, logging out user', [
                                'user_id' => Auth::id(),
                                'status' => $response->status()
                            ]);
                            
                            Auth::logout();
                            Session::flush();
                            
                            if ($request->expectsJson()) {
                                return response()->json(['error' => 'Session expired'], 401);
                            }
                            
                            return redirect(config('sso.project1.url') . '/login')->with('error', 'Your session has expired. Please login again.');
                        }
                        
                        // Update last check time
                        Session::put('last_sso_check', time());
                        
                    } catch (\Exception $e) {
                        Log::warning('SSO session verification failed', [
                            'error' => $e->getMessage(),
                            'user_id' => Auth::id()
                        ]);
                        
                        // Don't logout on network errors, just update the check time
                        // to avoid repeated failed requests
                        Session::put('last_sso_check', time());
                    }
                }
            }
        }

        return $next($request);
    }
}
