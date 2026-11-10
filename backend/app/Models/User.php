<?php

namespace App\Models;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<CompanyMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMember::class);
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_members')
            ->using(CompanyMember::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /** The user's membership in the given company, or null when the user does not belong to it. */
    public function membershipIn(Company|string $company): ?CompanyMember
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->memberships()->where('company_id', $companyId)->first();
    }

    /** The user's role in the given company, or null when the user does not belong to it. */
    public function roleIn(Company|string $company): ?CompanyRole
    {
        return $this->membershipIn($company)?->role;
    }
}
