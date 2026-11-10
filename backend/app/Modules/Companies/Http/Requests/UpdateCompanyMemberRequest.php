<?php

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Enums\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyMemberRequest extends FormRequest
{
    /** Authorization happens in the controller through CompanyMemberPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ];
    }

    public function role(): CompanyRole
    {
        return CompanyRole::from($this->validated('role'));
    }
}
