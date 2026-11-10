<?php

namespace App\Modules\Services\Http\Requests;

use App\Modules\Companies\Support\CurrentCompany;
use App\Modules\Services\Models\Service;
use App\Support\Http\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST creates; PATCH updates only the fields sent (including is_active and professional_ids). */
class SaveServiceRequest extends FormRequest
{
    use NormalizesInput;

    /** Runs before validation, so unauthorized users get 403 without learning the rules. */
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? $this->user()->can('update', $service)
            : $this->user()->can('create', [Service::class, $this->route('company')]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeColor();

        if ($this->has('category') && is_string($this->input('category'))) {
            $category = trim($this->input('category'));
            $this->merge(['category' => $category === '' ? null : $category]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'name' => $this->requiredUnlessPartial(['string', 'min:2', 'max:120']),
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            // Money is always integer cents (R$ 0,00 to R$ 100.000,00).
            'price_cents' => $this->requiredUnlessPartial(['integer', 'min:0', 'max:10000000']),
            'duration_minutes' => $this->requiredUnlessPartial(['integer', 'min:5', 'max:720', 'multiple_of:5']),
            'buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:240', 'multiple_of:5'],
            'color' => $this->requiredUnlessPartial(['string', 'regex:/^#[0-9A-F]{6}$/']),
            'is_active' => ['sometimes', 'boolean'],
            'professional_ids' => ['sometimes', 'array', 'max:200'],
            // Only professionals of the current company are accepted.
            'professional_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('professionals', 'id')->where('company_id', $companyId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'description' => 'descrição',
            'category' => 'categoria',
            'price_cents' => 'preço',
            'duration_minutes' => 'duração',
            'buffer_minutes' => 'intervalo',
            'color' => 'cor',
            'is_active' => 'ativo',
            'professional_ids' => 'profissionais',
            'professional_ids.*' => 'profissional',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['color.regex' => 'A cor deve estar no formato #RRGGBB.'];
    }
}
