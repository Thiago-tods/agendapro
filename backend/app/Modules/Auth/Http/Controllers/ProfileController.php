<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The signed-in user's own profile and password. */
class ProfileController
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], ['name' => 'nome', 'email' => 'e-mail']);

        $data['email'] = mb_strtolower(trim($data['email']));

        // The e-mail is the login: changing it requires the password, so a hijacked session
        // cannot take over the account.
        if ($data['email'] !== $user->email) {
            $request->validate(
                ['current_password' => ['required', 'current_password:web']],
                ['current_password.current_password' => 'A senha atual está incorreta.'],
                ['current_password' => 'senha atual'],
            );
        }
        $user->update($data);

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }

    /** Requires the current password, then ends every other session of this user. */
    public function updatePassword(Request $request): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.different' => 'A nova senha deve ser diferente da atual.',
        ], ['current_password' => 'senha atual', 'password' => 'nova senha']);

        $request->user()->update(['password' => $data['password']]);

        // Stateful SPA requests: invalidate the user's other sessions and rotate this one.
        if ($request->hasSession()) {
            Auth::guard('web')->logoutOtherDevices($data['password']);
            $request->session()->regenerate();
        }

        return response()->noContent();
    }
}
