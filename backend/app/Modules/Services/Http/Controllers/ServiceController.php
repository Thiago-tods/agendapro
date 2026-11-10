<?php

namespace App\Modules\Services\Http\Controllers;

use App\Modules\Companies\Models\Company;
use App\Modules\Services\Http\Requests\SaveServiceRequest;
use App\Modules\Services\Http\Resources\ServiceResource;
use App\Modules\Services\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Every query starts from $company->services(), and {service} is bound scoped to {company}.
 * Deactivation is a PATCH with is_active=false.
 */
class ServiceController
{
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Service::class, $company]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'professional_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $filters['status'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $services = $company->services()
            ->withCount('professionals')
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('category', 'ilike', $like));
            })
            ->when($filters['category'] ?? null, fn ($q, string $category) => $q->where('category', $category))
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when(
                $filters['professional_id'] ?? null,
                fn ($q, string $professionalId) => $q->whereHas('professionals', fn ($p) => $p->whereKey($professionalId)),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return ServiceResource::collection($services);
    }

    public function store(SaveServiceRequest $request, Company $company): ServiceResource
    {
        Gate::authorize('create', [Service::class, $company]);

        $service = DB::transaction(function () use ($request, $company): Service {
            $service = $company->services()->create(Arr::except($request->validated(), 'professional_ids'));
            $service->syncProfessionals($request->validated('professional_ids', []));

            return $service;
        });

        return new ServiceResource($service->refresh()->load('professionals'));
    }

    public function show(Company $company, Service $service): ServiceResource
    {
        Gate::authorize('view', $service);

        return new ServiceResource($service->load('professionals'));
    }

    public function update(SaveServiceRequest $request, Company $company, Service $service): ServiceResource
    {
        Gate::authorize('update', $service);

        DB::transaction(function () use ($request, $service): void {
            $service->update(Arr::except($request->validated(), 'professional_ids'));

            if ($request->has('professional_ids')) {
                $service->syncProfessionals($request->validated('professional_ids', []));
            }
        });

        return new ServiceResource($service->load('professionals'));
    }

    public function destroy(Company $company, Service $service): Response
    {
        Gate::authorize('delete', $service);

        abort_if(
            $service->hasOperationalHistory(),
            Response::HTTP_CONFLICT,
            'Este serviço possui histórico de atendimentos e não pode ser excluído. Desative-o.',
        );

        $service->delete();

        return response()->noContent();
    }
}
