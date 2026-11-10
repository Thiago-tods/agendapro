<?php

namespace App\Modules\Availability\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Full replacement of a weekly schedule (company or professional).
 * Several periods per weekday are allowed; the gaps between them are the breaks.
 * An empty list means "closed every day" for the company, or "follow the company hours"
 * for a professional.
 */
class SaveWeeklyHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAvailability', $this->route('company'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periods' => ['present', 'array', 'max:70'],
            'periods.*.weekday' => ['required', 'integer', 'between:0,6'],
            'periods.*.start_time' => ['required', 'date_format:H:i'],
            'periods.*.end_time' => ['required', 'date_format:H:i', 'after:periods.*.start_time'],
        ];
    }

    /** Periods of the same weekday must not overlap (touching is fine: 08:00-12:00 + 12:00-18:00). */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $byDay = collect($this->input('periods', []))->groupBy('weekday');

            foreach ($byDay as $weekday => $periods) {
                $sorted = $periods->sortBy('start_time')->values();

                for ($i = 1; $i < $sorted->count(); $i++) {
                    if ($sorted[$i]['start_time'] < $sorted[$i - 1]['end_time']) {
                        $validator->errors()->add('periods', "Há períodos sobrepostos no dia {$weekday}.");

                        return;
                    }
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'periods' => 'períodos',
            'periods.*.weekday' => 'dia da semana',
            'periods.*.start_time' => 'início',
            'periods.*.end_time' => 'fim',
        ];
    }
}
