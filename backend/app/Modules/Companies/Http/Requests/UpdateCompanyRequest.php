<?php

namespace App\Modules\Companies\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /** Authorization happens in the controller through CompanyPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(timezone_identifiers_list())],
            'currency' => ['sometimes', 'required', 'string', Rule::in(['BRL'])],
            'locale' => ['sometimes', 'required', 'string', Rule::in(['pt_BR'])],
        ];
    }
}
