<?php

namespace App\Modules\Professionals\Http\Requests;

use App\Modules\Companies\Support\CurrentCompany;
use App\Modules\Professionals\Models\Professional;
use App\Support\Http\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST creates; PATCH updates only the fields sent (including is_active and service_ids). */
class SaveProfessionalRequest extends FormRequest
{
    use NormalizesInput;

    /** Runs before validation, so unauthorized users get 403 without learning the rules. */
    public function authorize(): bool
    {
        $professional = $this->route('professional');

        return $professional instanceof Professional
            ? $this->user()->can('update', $professional)
            : $this->user()->can('create', [Professional::class, $this->route('company')]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['phone']);
        $this->normalizeEmails(['email']);
        $this->normalizeColor();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'name' => $this->requiredUnlessPartial(['string', 'min:2', 'max:120']),
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'digits_between:10,13'],
            'color' => $this->requiredUnlessPartial(['string', 'regex:/^#[0-9A-F]{6}$/']),
            'is_active' => ['sometimes', 'boolean'],
            'service_ids' => ['sometimes', 'array', 'max:200'],
            // Only services of the current company are accepted.
            'service_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('services', 'id')->where('company_id', $companyId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'title' => 'cargo',
            'bio' => 'descrição',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'color' => 'cor',
            'is_active' => 'ativo',
            'service_ids' => 'serviços',
            'service_ids.*' => 'serviço',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['color.regex' => 'A cor deve estar no formato #RRGGBB.'];
    }
}
