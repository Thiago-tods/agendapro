<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Modules\Companies\Http\Requests\UpdateCompanyMemberRequest;
use App\Modules\Companies\Http\Resources\CompanyMemberResource;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use App\Modules\Companies\Services\CompanyMembershipService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * {member} is bound scoped to {company}: a membership of another company resolves to 404.
 */
class CompanyMemberController
{
    public function __construct(private readonly CompanyMembershipService $memberships) {}

    public function index(Company $company): AnonymousResourceCollection
    {
        Gate::authorize('viewMembers', $company);

        $members = $company->members()
            ->with('user:id,name,email')
            ->join('users', 'users.id', '=', 'company_members.user_id')
            ->orderBy('users.name')
            ->select('company_members.*')
            ->get();

        return CompanyMemberResource::collection($members);
    }

    public function update(UpdateCompanyMemberRequest $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        $role = $request->role();

        Gate::authorize('update', [$member, $role]);

        $member = $this->memberships->changeRole($member, $role);

        return new CompanyMemberResource($member->load('user:id,name,email'));
    }

    public function destroy(Company $company, CompanyMember $member): Response
    {
        Gate::authorize('delete', $member);

        $this->memberships->remove($member);

        return response()->noContent();
    }
}
