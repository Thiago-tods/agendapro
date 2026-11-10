<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Every query starts from $company->customers(), and {customer} is bound scoped to {company}.
 */
class CustomerController
{
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Customer::class, $company]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $filters['status'] ?? 'active';

        $customers = $company->customers()
            ->search($filters['search'] ?? null)
            ->when($status === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    public function store(SaveCustomerRequest $request, Company $company): CustomerResource
    {
        Gate::authorize('create', [Customer::class, $company]);

        return new CustomerResource($company->customers()->create($request->validated()));
    }

    public function show(Company $company, Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(SaveCustomerRequest $request, Company $company, Customer $customer): CustomerResource
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function archive(Company $company, Customer $customer): CustomerResource
    {
        Gate::authorize('archive', $customer);

        if (! $customer->isArchived()) {
            $customer->forceFill(['archived_at' => now()])->save();
        }

        return new CustomerResource($customer);
    }

    public function restore(Company $company, Customer $customer): CustomerResource
    {
        Gate::authorize('archive', $customer);

        $customer->forceFill(['archived_at' => null])->save();

        return new CustomerResource($customer);
    }

    /** Permanent deletion is refused when operational history exists: archive instead. */
    public function destroy(Company $company, Customer $customer): Response
    {
        Gate::authorize('delete', $customer);

        abort_if(
            $customer->hasOperationalHistory(),
            Response::HTTP_CONFLICT,
            'Este cliente possui histórico de atendimentos e não pode ser excluído. Arquive-o.',
        );

        $customer->delete();

        return response()->noContent();
    }
}
