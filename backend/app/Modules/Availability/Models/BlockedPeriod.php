<?php

namespace App\Modules\Availability\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Absolute unavailable range [starts_at, ends_at) in UTC (vacation, meeting, maintenance).
 * professional_id NULL = blocks the whole company.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $professional_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string|null $reason
 * @property string|null $created_by
 */
class BlockedPeriod extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'professional_id', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }
}
