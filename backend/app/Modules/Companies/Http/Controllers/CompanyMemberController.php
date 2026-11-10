<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Models\User;
use App\Modules\Companies\Http\Requests\StoreCompanyMemberRequest;
use App\Modules\Companies\Http\Requests\UpdateCompanyMemberRequest;
use App\Modules\Companies\Http\Resources\CompanyMemberResource;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use App\Modules\Companies\Services\CompanyMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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

    public function store(StoreCompanyMemberRequest $request, Company $company): JsonResponse
    {
        $member = DB::transaction(function () use ($request, $company): CompanyMember {
            $user = $request->existingUser() ?? User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ]);

            if ($company->members()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Esta pessoa já faz parte da equipe.']);
            }

            return $company->members()->create(['user_id' => $user->id, 'role' => $request->role()]);
        });

        return (new CompanyMemberResource($member->load('user:id,name,email')))->response()->setStatusCode(201);
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
