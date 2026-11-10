<?php

namespace App\Modules\Companies\Models;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Policies\CompanyMemberPolicy;
use Database\Factories\CompanyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership of a user in a company, carrying the user's role there.
 *
 * @property string $id
 * @property string $company_id
 * @property string $user_id
 * @property CompanyRole $role
 */
#[Fillable(['company_id', 'user_id', 'role'])]
#[UseFactory(CompanyMemberFactory::class)]
#[UsePolicy(CompanyMemberPolicy::class)]
class CompanyMember extends Pivot
{
    /** @use HasFactory<CompanyMemberFactory> */
    use HasFactory, HasUuids;

    protected $table = 'company_members';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['role' => CompanyRole::class];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
