<?php

namespace App\Modules\Appointments\Http\Requests;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape validation only. Business rules (hours, blocks, overlap, active records) are checked
 * by AppointmentService inside the booking transaction, against current data.
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Appointment::class, $this->route('company')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'professional_id' => ['required', 'uuid', Rule::exists('professionals', 'id')->where('company_id', $companyId)],
            'service_id' => ['required', 'uuid', Rule::exists('services', 'id')->where('company_id', $companyId)],
            // ISO 8601. Without an offset, the time is read in the company's timezone.
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'cliente',
            'professional_id' => 'profissional',
            'service_id' => 'serviço',
            'starts_at' => 'início',
            'notes' => 'observações',
        ];
    }
}
