<?php

namespace App\Modules\Companies\Models;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Availability\Models\WorkingHour;
use App\Modules\Companies\Policies\CompanyPolicy;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property string $currency
 * @property string $locale
 */
#[Fillable(['name', 'slug', 'timezone', 'currency', 'locale'])]
#[UseFactory(CompanyFactory::class)]
#[UsePolicy(CompanyPolicy::class)]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasUuids;

    /** @return HasMany<CompanyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CompanyMember::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<AvailabilityException, $this> */
    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    /** @return HasMany<BlockedPeriod, $this> */
    public function blockedPeriods(): HasMany
    {
        return $this->hasMany(BlockedPeriod::class);
    }

    /** @return HasMany<WorkingHour, $this> */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Professional, $this> */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_members')
            ->using(CompanyMember::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }
}
