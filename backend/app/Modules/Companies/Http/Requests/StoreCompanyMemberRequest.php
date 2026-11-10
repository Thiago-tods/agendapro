<?php

namespace App\Modules\Companies\Http\Requests;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\CompanyMember;
use App\Support\Http\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Adds a user to the team. An existing account (by e-mail) is attached as is; otherwise a new
 * account is created with the name and initial password given, which the person changes later.
 */
class StoreCompanyMemberRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        $role = CompanyRole::tryFrom((string) $this->input('role')) ?? CompanyRole::Professional;

        return $this->user()->can('create', [CompanyMember::class, $this->route('company'), $role]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmails(['email']);
    }

    public function existingUser(): ?User
    {
        return User::query()->where('email', $this->input('email'))->first();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isNew = $this->existingUser() === null;

        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::enum(CompanyRole::class)],
            'name' => $isNew ? ['required', 'string', 'min:2', 'max:120'] : ['prohibited'],
            'password' => $isNew ? ['required', 'string', Password::min(8)->letters()->numbers()] : ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.prohibited' => 'Este e-mail já tem conta: o nome não pode ser alterado aqui.',
            'password.prohibited' => 'Este e-mail já tem conta: a senha não pode ser definida aqui.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'e-mail', 'role' => 'papel', 'name' => 'nome', 'password' => 'senha inicial'];
    }

    public function role(): CompanyRole
    {
        return CompanyRole::from($this->validated('role'));
    }
}
