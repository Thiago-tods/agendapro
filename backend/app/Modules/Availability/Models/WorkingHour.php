<?php

namespace App\Modules\Availability\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Weekly recurring period in the company's local time.
 * professional_id NULL = company business hours.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $professional_id
 * @property int $weekday 0 = Sunday ... 6 = Saturday
 * @property string $start_time HH:MM:SS
 * @property string $end_time HH:MM:SS
 */
class WorkingHour extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'professional_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
