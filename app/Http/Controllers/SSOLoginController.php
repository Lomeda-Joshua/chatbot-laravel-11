<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;

class SSOLoginController extends Controller
{
    public function authenticate(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            Log::warning('SSO Authentication: No token provided');
            return redirect($this->unifiedLoginUrl())->with('error', 'No authentication token provided.');
        }

        try {
            $verifyUrl = config('sso.project1.url') . '/api/sso/verify-token';
            Log::info('SSO Authentication Attempt', [
                'token_preview' => substr($token, 0, 20) . '...',
                'verify_url' => $verifyUrl,
                'environment' => config('app.env')
            ]);
            
            $response = Http::timeout(10)->post($verifyUrl, [
                'token' => $token
            ]);

            if (!$response->successful()) {
                Log::error('SSO Token Verification Failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'verify_url' => $verifyUrl
                ]);
                return redirect($this->unifiedLoginUrl())->with('error', 'Invalid or expired authentication token.');
            }

            $responseData = $response->json();
            $userData = $responseData['user'] ?? null;

            if (!$userData) {
                Log::error('SSO No User Data', ['response' => $responseData]);
                return redirect($this->unifiedLoginUrl())->with('error', 'No user data returned from SSO.');
            }

            $user = User::where('email', $userData['email'])->first();

            if (!$user) {
                Log::info('SSO Creating New User', ['email' => $userData['email'], 'user_data' => $userData]);
                
                try {
                    $user = User::create([
                        'name' => $userData['name'] ?? $userData['email'],
                        'email' => $userData['email'],
                        'password' => bcrypt(Str::random(16)),
                        'email_verified_at' => now(),
                    ]);
                    
                    Log::info('SSO User Created Successfully', ['user_id' => $user->id]);
                } catch (\Exception $e) {
                    Log::error('SSO User Creation Failed', [
                        'error' => $e->getMessage(),
                        'user_data' => $userData,
                        'trace' => $e->getTraceAsString()
                    ]);
                    return redirect($this->unifiedLoginUrl())->with('error', 'Failed to create user account. Please contact support.');
                }
            }

            Auth::login($user);

            Session::put('sso_token', $token);
            Session::put('sso_authenticated', true);
            Session::put('last_sso_check', time());
            Session::forget('url.intended');

            Log::info('SSO Authentication Successful', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return redirect()->intended('/')->with('success', 'Logged in successfully with SSO.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('SSO Database Error', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString()
            ]);
            return redirect($this->unifiedLoginUrl())->with('error', 'Database error during SSO login. Please contact support.');
        } catch (\Exception $e) {
            Log::error('SSO Authentication Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'verify_url' => config('sso.project1.url') . '/api/sso/verify-token'
            ]);
            return redirect($this->unifiedLoginUrl())->with('error', 'SSO login failed. Please try again.');
        }
    }

    public function loginWithToken(Request $request)
    {
        $token = $request->query('token');
        
        if (Auth::check() && !Session::get('sso_authenticated')) {
            Log::info('SSO Auto-login: User already logged in locally to chatbotapi', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email,
                'adding_sso_token' => $token ? 'yes' : 'no',
            ]);
            
            if ($token) {
                try {
                    $verifyUrl = config('sso.project1.url') . '/api/sso/verify-token';
                    $response = Http::timeout(10)->post($verifyUrl, ['token' => $token]);
                    
                    if ($response->successful()) {
                        $responseData = $response->json();
                        $userData = $responseData['user'] ?? null;
                        
                        if ($userData && $userData['email'] === Auth::user()->email) {
                            Session::put('sso_token', $token);
                            Session::put('sso_authenticated', true);
                            Session::put('last_sso_check', time());
                            Log::info('SSO Auto-login: Added SSO session to existing local auth');
                        } else {
                            Log::warning('SSO Auto-login: Token email mismatch with local user', [
                                'local_email' => Auth::user()->email,
                                'sso_email' => $userData['email'] ?? 'N/A',
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('SSO Auto-login: Failed to verify token for existing session', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            Session::forget('url.intended');
            return redirect()->intended('/');
        }
        
        if (Auth::check() && Session::get('sso_authenticated') && Session::get('sso_token') === $token) {
            Log::info('SSO Auto-login: Already authenticated with same SSO token', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email,
            ]);
            return redirect()->intended('/');
        }
        
        if (!$token) {
            Log::warning('SSO Auto-login: No token and not authenticated');
            return redirect($this->unifiedLoginUrl());
        }

        try {
            $verifyUrl = config('sso.project1.url') . '/api/sso/verify-token';
            Log::info('SSO Auto-login Attempt', [
                'token_preview' => substr($token, 0, 20) . '...',
                'has_existing_auth' => Auth::check()
            ]);
            
            $response = Http::timeout(10)->post($verifyUrl, [
                'token' => $token,
            ]);

            if (!$response->successful()) {
                Log::error('SSO Auto-login Token Verification Failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return redirect($this->unifiedLoginUrl())->with('error', 'Invalid or expired SSO token.');
            }

            $responseData = $response->json();
            $userData = $responseData['user'] ?? null;

            if (!$userData) {
                Log::error('SSO Auto-login No User Data');
                return redirect($this->unifiedLoginUrl())->with('error', 'No user data from SSO.');
            }

            $user = User::where('email', $userData['email'])->first();

            if (!$user) {
                Log::info('SSO Auto-login Creating New User', ['email' => $userData['email']]);

                try {
                    $user = User::create([
                        'name' => $userData['name'] ?? $userData['email'],
                        'email' => $userData['email'],
                        'password' => bcrypt(Str::random(16)),
                        'email_verified_at' => now(),
                    ]);

                    Log::info('SSO Auto-login User Created', ['user_id' => $user->id]);
                } catch (\Exception $e) {
                    Log::error('SSO Auto-login User Creation Failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return redirect($this->unifiedLoginUrl())->with('error', 'Failed to create user account.');
                }
            }

            Auth::login($user);
            $request->session()->regenerate();
            Session::put('sso_token', $token);
            Session::put('sso_authenticated', true);
            Session::put('last_sso_check', time());
            Session::forget('url.intended');

            Log::info('SSO Auto-login Successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()->intended('/')->with('success', 'Logged in via SSO.');
        } catch (\Exception $e) {
            Log::error('SSO Auto-login Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect($this->unifiedLoginUrl())->with('error', 'SSO auto-login failed.');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        Session::flush();
        Log::info('SSO Logout', ['user_id' => Auth::id() ?? 'unknown']);
        return redirect($this->unifiedLoginUrl());
    }

    private function unifiedLoginUrl(): string
    {
        return rtrim(config('sso.project1.url'), '/') . '/login';
    }
}
