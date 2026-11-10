<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Companies\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Cookie-based session authentication for the first-party SPA (Sanctum stateful API).
 * No API tokens are issued: the session cookie is HttpOnly and CSRF-protected.
 */
class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            $request->hitRateLimiter();

            // Same message for unknown e-mail and wrong password: no account enumeration.
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate(); // prevents session fixation

        return $this->session($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /** Restores the SPA session: the user and every company they belong to, with role and permissions. */
    public function me(Request $request): JsonResponse
    {
        return $this->session($request->user());
    }

    private function session(User $user): JsonResponse
    {
        $companies = $user->companies()->orderBy('name')->get();

        return response()->json([
            'data' => [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'companies' => CompanyResource::collection($companies),
            ],
        ]);
    }
}
