<?php

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Confirm, check-in, complete, no-show (optional reason) and cancel (reason required). */
class ChangeAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('appointment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isCancel = str_ends_with($this->path(), '/cancel');

        return [
            'reason' => [$isCancel ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'motivo'];
    }
}
