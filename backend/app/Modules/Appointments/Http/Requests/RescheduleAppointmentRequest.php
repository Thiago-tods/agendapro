<?php

namespace App\Modules\Appointments\Http\Requests;

use App\Modules\Companies\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('appointment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'starts_at' => ['required', 'date'],
            'professional_id' => ['sometimes', 'uuid', Rule::exists('professionals', 'id')->where('company_id', $companyId)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['starts_at' => 'início', 'professional_id' => 'profissional', 'reason' => 'motivo'];
    }
}
