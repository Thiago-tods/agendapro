<?php

namespace App\Modules\Professionals\Models;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Policies\ProfessionalPolicy;
use App\Modules\Services\Models\Service;
use Database\Factories\ProfessionalFactory;
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
 * @property string|null $title
 * @property string|null $bio
 * @property string|null $email
 * @property string|null $phone
 * @property string $color
 * @property bool $is_active
 */
#[Fillable(['name', 'title', 'bio', 'email', 'phone', 'color', 'is_active'])]
#[UseFactory(ProfessionalFactory::class)]
#[UsePolicy(ProfessionalPolicy::class)]
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'professional_service');
    }

    /**
     * Replace the associated services. IDs must already be validated as belonging to this company;
     * the composite foreign keys reject anything else at the database level.
     *
     * @param  list<string>  $serviceIds
     */
    public function syncServices(array $serviceIds): void
    {
        $this->services()->syncWithPivotValues($serviceIds, ['company_id' => $this->company_id]);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Whether the professional has appointments that must be preserved (then deactivate instead of delete). */
    public function hasOperationalHistory(): bool
    {
        return $this->appointments()->exists();
    }
}
