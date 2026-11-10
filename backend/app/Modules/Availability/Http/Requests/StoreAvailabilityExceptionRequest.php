<?php

namespace App\Modules\Availability\Http\Requests;

use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Companies\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One exception row for a local date. Without professional_id it applies to the whole company.
 * A date (per scope) is either closed or has replacement ranges, never both.
 */
class StoreAvailabilityExceptionRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'is_closed' => ['required', 'boolean'],
            'start_time' => ['exclude_if:is_closed,true', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_if:is_closed,true', 'required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existing = AvailabilityException::query()
                ->where('company_id', app(CurrentCompany::class)->id())
                ->where('professional_id', $this->input('professional_id'))
                ->whereDate('date', $this->input('date'))
                ->get();

            if ($existing->isEmpty()) {
                return;
            }

            if ($this->boolean('is_closed') || $existing->contains('is_closed', true)) {
                $validator->errors()->add('date', 'Já existe uma exceção para esta data. Remova-a antes de criar outra incompatível.');

                return;
            }

            $start = $this->input('start_time').':00';
            $end = $this->input('end_time').':00';

            if ($existing->contains(fn (AvailabilityException $e): bool => $start < $e->end_time && $end > $e->start_time)) {
                $validator->errors()->add('start_time', 'O intervalo se sobrepõe a outra exceção desta data.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'professional_id' => 'profissional',
            'date' => 'data',
            'is_closed' => 'fechado',
            'start_time' => 'início',
            'end_time' => 'fim',
            'reason' => 'motivo',
        ];
    }
}
