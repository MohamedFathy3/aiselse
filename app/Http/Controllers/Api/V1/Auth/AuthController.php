<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Session-based authentication for the SPA via Laravel Sanctum.
 *
 * The Next.js frontend must first GET /sanctum/csrf-cookie, then POST here.
 * We deliberately use Sanctum's stateful cookie flow rather than issuing
 * bearer tokens, so nothing resembling an auth token is ever stored in
 * localStorage on the frontend (see spec section 42).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();

        $request->session()->regenerate();

        return new UserResource(Auth::user());
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function forgotPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill(['password' => $request->string('password')])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }
}
