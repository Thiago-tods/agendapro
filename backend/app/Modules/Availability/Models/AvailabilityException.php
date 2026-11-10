<?php

namespace App\Modules\Availability\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Overrides the weekly hours on one local date: closed all day, or open only in the given range(s).
 * professional_id NULL = applies to the whole company.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $professional_id
 * @property Carbon $date
 * @property bool $is_closed
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $reason
 * @property string|null $created_by
 */
class AvailabilityException extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'professional_id', 'date', 'is_closed', 'start_time', 'end_time', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_closed' => 'boolean'];
    }
}
