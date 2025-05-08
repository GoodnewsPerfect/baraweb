<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Notifications\Notifiable;
use App\Notifications\PasswordReset;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Google_Client;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'verifyEmail', 'forgotPassword', 'resetPassword']]);
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|confirmed|min:6',
                'is_admin' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::create(array_merge(
                $validator->validated(),
                ['password' => bcrypt($request->password)]
            ));

            $user->notify(new VerifyEmail);

            return response()->json([
                'message' => 'User successfully registered',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred during registration'], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            if (!$token = auth()->attempt($validator->validated())) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

        // Update last login timestamp
        auth()->user()->update([
            'last_login_at' => now()
        ]);

            return $this->createNewToken($token);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred during login'], 500);
        }
    }

    public function profile()
    {
        try {
            $user = Auth::user()->load([
                'addressInfo',
                'orders',
                'transactions',
                'reviews',
                'favorites',
                'notifications'
            ]);
    
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user,
                    'verified' => $user->hasVerifiedEmail(),
                    'joined_date' => $user->created_at->format('Y-m-d'),
                    'last_login' => $user->last_login_at
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Profile retrieval error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving profile data'
            ], 500);
        }
    }
    
    public function logout()
    {
        try {
            auth()->logout();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred during logout'], 500);
        }
    }

    public function refresh()
    {
        try {
            return $this->createNewToken(auth()->refresh());
        } catch (\Exception $e) {
            Log::error('Token refresh error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while refreshing token'], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        try {
            $user = User::find($request->route('id'));

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
                return response()->json(['message' => 'Invalid verification link'], 400);
            }

            if ($user->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email already verified'], 400);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return response()->json(['message' => 'Email verified successfully']);
        } catch (\Exception $e) {
            Log::error('Email verification error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred during email verification'], 500);
        }
    }

    public function resendVerificationEmail(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email already verified'], 400);
            }

            $user->notify(new VerifyEmail);

            return response()->json(['message' => 'Verification email resent']);
        } catch (\Exception $e) {
            Log::error('Resend verification email error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while resending verification email'], 500);
        }
    }

    protected function createNewToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user(),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
    
            // Get user by email
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
    
            // Generate reset token
            $token = Password::createToken($user);
            
            // Send the custom notification
            $user->notify(new PasswordReset($token));
    
            return response()->json([
                'status' => 'success',
                'message' => 'Reset password link sent to your email'
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process password reset request'
            ], 500);
        }
    }
    
    
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ]);
                $user->save();
                event(new PasswordReset($user));
            }
        );
    
        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully'])
            : response()->json(['message' => 'Unable to reset password'], 400);
    }
    
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|confirmed|min:6|different:current_password',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $user = Auth::user();
    
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 401);
        }
    
        $user->password = Hash::make($request->password);
        $user->save();
    
        return response()->json(['message' => 'Password changed successfully']);
    }

    public function googleLogin(Request $request)
    {
        // Log the incoming request for debugging
        Log::debug('Google Login Request Received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $request->all()
        ]);
    
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            Log::error('Google Login Validation Failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'received_token_length' => strlen($request->id_token),
                    'token_prefix' => substr($request->id_token, 0, 20) . '...'
                ]
            ], 422);
        }
    
        // Verify Google Client ID is set
        $clientId = env('GOOGLE_CLIENT_ID');
        if (empty($clientId)) {
            Log::critical('Google Client ID not configured');
            return response()->json([
                'status' => 'error',
                'message' => 'Server configuration error',
                'details' => 'Google OAuth client ID not configured'
            ], 500);
        }
    
        $client = new Google_Client(['client_id' => $clientId]);
    
        try {
            Log::debug('Attempting to verify Google ID token', [
                'client_id' => $clientId,
                'token_prefix' => substr($request->id_token, 0, 20) . '...'
            ]);
    
            $payload = $client->verifyIdToken($request->id_token);
    
            if (!$payload) {
                Log::error('Google Token Verification Failed', [
                    'token_invalid' => true,
                    'token_length' => strlen($request->id_token),
                    'client_id_used' => $clientId
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid ID token',
                    'debug_info' => [
                        'token_validation_failed' => true,
                        'possible_causes' => [
                            'Expired token',
                            'Incorrect audience (client ID)',
                            'Token malformed'
                        ],
                        'expected_client_id' => $clientId
                    ]
                ], 401);
            }
    
            Log::debug('Google Token Verified Successfully', [
                'payload_keys' => array_keys($payload),
                'email' => $payload['email'] ?? null,
                'email_verified' => $payload['email_verified'] ?? null,
                'issuer' => $payload['iss'] ?? null
            ]);
    
            $email = $payload['email'] ?? null;
            $firstName = $payload['given_name'] ?? '';
            $lastName = $payload['family_name'] ?? '';
    
            if (!$email) {
                Log::error('Google Token Missing Email', ['payload' => $payload]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Google token does not contain email',
                    'payload_keys' => array_keys($payload)
                ], 400);
            }
    
            // Find or create user
            try {
                $user = User::where('email', $email)->first();
    
                if (!$user) {
                    Log::info('Creating new user from Google login', ['email' => $email]);
                    $user = User::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'password' => bcrypt(Str::random(32)),
                        'email_verified_at' => now(),
                    ]);
                } else {
                    Log::debug('Existing user found for Google login', ['user_id' => $user->id]);
                }
            } catch (\Exception $e) {
                Log::error('User Creation/Retrieval Failed', [
                    'error' => $e->getMessage(),
                    'email' => $email,
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'User account processing failed',
                    'error' => $e->getMessage(),
                    'error_details' => [
                        'type' => get_class($e),
                        'email_attempted' => $email
                    ]
                ], 500);
            }
    
            // Attempt to login the user
            try {
                $token = auth()->login($user);
                Log::info('Google Login Successful', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
    
                return $this->createNewToken($token);
    
            } catch (\Exception $e) {
                Log::error('JWT Token Generation Failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'auth_config' => config('auth')
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication token generation failed',
                    'error' => $e->getMessage(),
                    'debug_info' => [
                        'possible_causes' => [
                            'JWT secret not set',
                            'Auth guard misconfiguration',
                            'User model issues'
                        ]
                    ]
                ], 500);
            }
    
        } catch (\Exception $e) {
            Log::error('Google Login Processing Error', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Google authentication processing failed',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'debug_info' => [
                    'google_client_id_used' => $clientId,
                    'token_length' => strlen($request->id_token),
                    'token_prefix' => substr($request->id_token, 0, 20) . '...',
                    'possible_causes' => $this->getPossibleCauses($e)
                ]
            ], 500);
        }
    }
    
    protected function getPossibleCauses(\Exception $e): array
    {
        $causes = [];
        
        if (str_contains($e->getMessage(), 'OpenSSL unable to verify data')) {
            $causes[] = 'Possible certificate verification issue - check server time and CA certificates';
        }
        
        if (str_contains($e->getMessage(), 'Wrong number of segments')) {
            $causes[] = 'Malformed JWT token - check token format';
        }
        
        if (str_contains($e->getMessage(), 'Signature verification failed')) {
            $causes[] = 'Token signature invalid - possible client ID mismatch or token tampering';
        }
        
        if (empty($causes)) {
            $causes[] = 'Undetermined cause - check server logs for more details';
        }
        
        return $causes;
    }

}


