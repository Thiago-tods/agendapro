<?php

namespace App\Modules\Services\Models;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Policies\ServicePolicy;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property int $price_cents
 * @property int $duration_minutes
 * @property int $buffer_minutes
 * @property string $color
 * @property bool $is_active
 */
#[Fillable(['name', 'description', 'category', 'price_cents', 'duration_minutes', 'buffer_minutes', 'color', 'is_active'])]
#[UseFactory(ServiceFactory::class)]
#[UsePolicy(ServicePolicy::class)]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['is_active' => true, 'buffer_minutes' => 0];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsToMany<Professional, $this> */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class, 'professional_service');
    }

    /** @param  list<string>  $professionalIds  already validated as belonging to this company */
    public function syncProfessionals(array $professionalIds): void
    {
        $this->professionals()->syncWithPivotValues($professionalIds, ['company_id' => $this->company_id]);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Whether the service has appointments that must be preserved (then deactivate instead of delete). */
    public function hasOperationalHistory(): bool
    {
        return $this->appointments()->exists();
    }
}
