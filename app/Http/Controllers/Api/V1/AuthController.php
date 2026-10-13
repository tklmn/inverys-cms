<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\TwoFactorCodeMail;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'token_name' => 'sometimes|string|max:255',
            'two_factor_code' => 'sometimes|string',
            'use_recovery' => 'sometimes|boolean',
        ]);

        // The same lockout after failed attempts as for the browser login
        // (security.login_max_attempts / login_lockout_duration, key email|IP)
        $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
        $maxAttempts = max(3, (int) setting('security.login_max_attempts', '5'));
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many login attempts.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }
        $fail = function (string $message) use ($throttleKey): JsonResponse {
            RateLimiter::hit($throttleKey, max(1, (int) setting('security.login_lockout_duration', '1')) * 60);

            return response()->json(['message' => $message], 401);
        };

        $user = User::where('email', $request->email)->first();
        $password = (string) $request->input('password');
        $hash = $user !== null && is_string($user->password) && $user->password !== '' ? $user->password : null;

        // Without an account a hash is computed all the same, so the response
        // time does not tell which addresses have one (the browser login and
        // registration hide this as well)
        if ($hash === null) {
            Hash::make($password);

            return $fail('Invalid credentials.');
        }

        if (! Hash::check($password, $hash)) {
            return $fail('Invalid credentials.');
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account inactive.'], 403);
        }

        // Enforce two-factor authentication over the API just like the web login does.
        if ($twoFactor->isRequired($user)) {
            $challenge = $this->twoFactorChallenge($request, $twoFactor, $user);
            if ($challenge !== null) {
                // A wrong code counts like a wrong password
                if ($challenge->getStatusCode() === 401) {
                    RateLimiter::hit($throttleKey, max(1, (int) setting('security.login_lockout_duration', '1')) * 60);
                }

                return $challenge;
            }
        }

        RateLimiter::clear($throttleKey);

        $result = ApiToken::createToken(
            $user,
            $request->token_name ?? 'api-token',
            ['*'],
            60 * 24 // 24 hours
        );

        return response()->json([
            'message' => 'Login successful.',
            'token' => $result['plain_text'],
            'expires_at' => $result['token']->expires_at?->toISOString(),
            'user' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Verify the second factor for an API login.
     *
     * Returns a JsonResponse when the challenge is unmet (code required or invalid);
     * returns null when verification succeeds and a token may be issued.
     */
    private function twoFactorChallenge(Request $request, TwoFactorService $twoFactor, User $user): ?JsonResponse
    {
        $method = $twoFactor->userMethod($user);
        $code = $request->filled('two_factor_code') ? trim((string) $request->input('two_factor_code')) : null;

        if ($code === null) {
            // No code supplied yet — for the email method, send one so the client can resubmit.
            // At most one mail per minute and user, so the endpoint cannot flood an inbox.
            if ($method === 'email' && RateLimiter::attempt('api-2fa-mail:'.$user->getKey(), 1, fn () => true, 60)) {
                $emailCode = $twoFactor->generateEmailCode($user);
                $lifetime = (int) setting('security.two_factor_code_lifetime', '10');
                Mail::to($user)->send(new TwoFactorCodeMail($emailCode, $lifetime));
            }

            return response()->json([
                'message' => 'Two-factor authentication required.',
                'two_factor_required' => true,
                'method' => $method,
            ], 202);
        }

        // Failed attempts also count per account (shared with the browser
        // login) — the lockout in login() is tied to the IP and could be
        // bypassed with changing addresses
        if ($twoFactor->tooManyFailedAttempts($user)) {
            return response()->json([
                'message' => 'Too many invalid two-factor codes.',
                'retry_after' => $twoFactor->failedAttemptsAvailableIn($user),
            ], 429);
        }

        $valid = $request->boolean('use_recovery')
            ? $twoFactor->verifyRecoveryCode($user, $code)
            : match ($method) {
                'totp' => $twoFactor->verifyTotp($user->two_factor_secret, $code),
                default => $twoFactor->verifyEmailCode($user, $code),
            };

        if (! $valid) {
            $twoFactor->recordFailedAttempt($user);

            return response()->json([
                'message' => 'Invalid two-factor code.',
                'two_factor_required' => true,
                'method' => $method,
            ], 401);
        }

        $twoFactor->clearFailedAttempts($user);

        return null;
    }

    public function logout(Request $request): JsonResponse
    {
        $apiToken = $request->get('api_token');
        if ($apiToken) {
            $apiToken->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load('roles'));
    }
}
