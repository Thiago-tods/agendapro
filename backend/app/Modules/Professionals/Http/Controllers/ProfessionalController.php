<?php

namespace App\Modules\Professionals\Http\Controllers;

use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Http\Requests\SaveProfessionalRequest;
use App\Modules\Professionals\Http\Resources\ProfessionalResource;
use App\Modules\Professionals\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Every query starts from $company->professionals(), and {professional} is bound scoped to {company}.
 * Deactivation is a PATCH with is_active=false.
 */
class ProfessionalController
{
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Professional::class, $company]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'service_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $filters['status'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $professionals = $company->professionals()
            ->withCount('services')
            ->when($search !== '', fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when(
                $filters['service_id'] ?? null,
                fn ($q, string $serviceId) => $q->whereHas('services', fn ($s) => $s->whereKey($serviceId)),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return ProfessionalResource::collection($professionals);
    }

    public function store(SaveProfessionalRequest $request, Company $company): ProfessionalResource
    {
        Gate::authorize('create', [Professional::class, $company]);

        $professional = DB::transaction(function () use ($request, $company): Professional {
            $professional = $company->professionals()->create(Arr::except($request->validated(), 'service_ids'));
            $professional->syncServices($request->validated('service_ids', []));

            return $professional;
        });

        return new ProfessionalResource($professional->load('services'));
    }

    public function show(Company $company, Professional $professional): ProfessionalResource
    {
        Gate::authorize('view', $professional);

        return new ProfessionalResource($professional->load('services'));
    }

    public function update(SaveProfessionalRequest $request, Company $company, Professional $professional): ProfessionalResource
    {
        Gate::authorize('update', $professional);

        DB::transaction(function () use ($request, $professional): void {
            $professional->update(Arr::except($request->validated(), 'service_ids'));

            if ($request->has('service_ids')) {
                $professional->syncServices($request->validated('service_ids', []));
            }
        });

        return new ProfessionalResource($professional->load('services'));
    }

    public function destroy(Company $company, Professional $professional): Response
    {
        Gate::authorize('delete', $professional);

        abort_if(
            $professional->hasOperationalHistory(),
            Response::HTTP_CONFLICT,
            'Este profissional possui histórico de atendimentos e não pode ser excluído. Desative-o.',
        );

        $professional->delete();

        return response()->noContent();
    }
}
