<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Modules\Companies\Http\Requests\UpdateCompanyRequest;
use App\Modules\Companies\Http\Resources\CompanyResource;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CompanyController
{
    /** Companies the authenticated user belongs to, with the user's role in each. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $companies = $request->user()->companies()->orderBy('name')->get();

        return CompanyResource::collection($companies);
    }

    public function show(Company $company, CurrentCompany $current): CompanyResource
    {
        Gate::authorize('view', $company);

        return (new CompanyResource($company))->withMembership($current->membership());
    }

    public function update(UpdateCompanyRequest $request, Company $company, CurrentCompany $current): CompanyResource
    {
        Gate::authorize('update', $company);

        // Only validated fields: a company_id or any other key in the payload is ignored.
        $company->update($request->validated());

        return (new CompanyResource($company))->withMembership($current->membership());
    }
}
