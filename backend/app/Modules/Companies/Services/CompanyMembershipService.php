<?php

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\CompanyMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membership changes that must keep every company with at least one owner.
 * Owner rows are locked so concurrent demotions cannot leave a company ownerless.
 */
class CompanyMembershipService
{
    public function changeRole(CompanyMember $member, CompanyRole $role): CompanyMember
    {
        return DB::transaction(function () use ($member, $role): CompanyMember {
            if ($member->role === CompanyRole::Owner && $role !== CompanyRole::Owner) {
                $this->ensureAnotherOwnerExists($member, 'role');
            }

            $member->update(['role' => $role]);

            return $member;
        });
    }

    public function remove(CompanyMember $member): void
    {
        DB::transaction(function () use ($member): void {
            if ($member->role === CompanyRole::Owner) {
                $this->ensureAnotherOwnerExists($member, 'member');
            }

            $member->delete();
        });
    }

    private function ensureAnotherOwnerExists(CompanyMember $member, string $field): void
    {
        $owners = CompanyMember::query()
            ->where('company_id', $member->company_id)
            ->where('role', CompanyRole::Owner)
            ->lockForUpdate()
            ->pluck('id');

        if ($owners->reject(fn (string $id): bool => $id === $member->id)->isEmpty()) {
            throw ValidationException::withMessages([
                $field => 'A empresa precisa ter pelo menos um proprietário.',
            ]);
        }
    }
}
