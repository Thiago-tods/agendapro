<?php

namespace App\Modules\Customers\Models;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Policies\CustomerPolicy;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property string|null $notes
 * @property Carbon|null $archived_at
 */
#[Fillable(['name', 'email', 'phone', 'birth_date', 'notes'])]
#[UseFactory(CustomerFactory::class)]
#[UsePolicy(CustomerPolicy::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Whether the customer has appointments that must be preserved (then archive instead of delete). */
    public function hasOperationalHistory(): bool
    {
        return $this->appointments()->exists();
    }

    /**
     * Case-insensitive search by name or e-mail; digits in the term also match the phone.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $digits = preg_replace('/\D/', '', $term);

        $query->where(function (Builder $q) use ($like, $digits): void {
            $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like);

            if (strlen((string) $digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }
}
