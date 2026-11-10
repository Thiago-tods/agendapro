<?php

namespace App\Modules\Availability\Http\Requests;

use App\Modules\Companies\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Times are ISO 8601; without an offset they are read in the company's timezone.
 * Without professional_id the block applies to the whole company.
 */
class StoreBlockedPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAvailability', $this->route('company'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'professional_id' => ['nullable', 'uuid', Rule::exists('professionals', 'id')->where('company_id', $companyId)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->endsAt()->greaterThan($this->startsAt())) {
                $validator->errors()->add('ends_at', 'O fim deve ser posterior ao início.');
            }
        }];
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->parse('starts_at');
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->parse('ends_at');
    }

    private function parse(string $field): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input($field), app(CurrentCompany::class)->company()->timezone)->utc();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['professional_id' => 'profissional', 'starts_at' => 'início', 'ends_at' => 'fim', 'reason' => 'motivo'];
    }
}
