<?php

namespace App\Modules\Appointments\Http\Controllers;

use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Http\Requests\ChangeAppointmentStatusRequest;
use App\Modules\Appointments\Http\Requests\RescheduleAppointmentRequest;
use App\Modules\Appointments\Http\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Http\Resources\AppointmentResource;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * {appointment} and {customer} are bound scoped to {company}. There is no destroy action.
 */
class AppointmentController
{
    private const WITH = ['customer:id,name,phone', 'professional:id,name,color'];

    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * Appointments whose service time intersects the local date range [from, to] (inclusive days),
     * in the company's timezone. Ordered by start time. Not paginated: bounded to 62 days.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Appointment::class, $company]);

        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'professional_id' => ['nullable', 'uuid'],
            'service_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(AppointmentStatus::class)],
        ]);

        $from = CarbonImmutable::parse($filters['from'], $company->timezone)->startOfDay();
        $until = CarbonImmutable::parse($filters['to'], $company->timezone)->addDay()->startOfDay();

        abort_if($from->diffInDays($until) > 62, 422, 'O período consultado deve ter no máximo 62 dias.');

        $appointments = $company->appointments()
            ->with(self::WITH)
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->when($filters['professional_id'] ?? null, fn ($q, string $id) => $q->where('professional_id', $id))
            ->when($filters['service_id'] ?? null, fn ($q, string $id) => $q->where('service_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($q, string $id) => $q->where('customer_id', $id))
            ->when($filters['status'] ?? null, fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return AppointmentResource::collection($appointments);
    }

    /** Visit history of one customer, most recent first. */
    public function forCustomer(Request $request, Company $company, Customer $customer): AnonymousResourceCollection
    {
        Gate::authorize('view', $customer);

        $perPage = (int) ($request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']])['per_page'] ?? 25);

        $appointments = $customer->appointments()
            ->with(self::WITH)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request, Company $company): JsonResponse
    {
        $appointment = $this->appointments->create($company, $request->user(), $request->validated());

        return (new AppointmentResource($appointment->load([...self::WITH, 'events.actor:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('view', $appointment);

        return new AppointmentResource($appointment->load([...self::WITH, 'events.actor:id,name']));
    }

    public function reschedule(RescheduleAppointmentRequest $request, Company $company, Appointment $appointment): AppointmentResource
    {
        $appointment = $this->appointments->reschedule(
            $appointment,
            $request->user(),
            $request->validated('starts_at'),
            $request->validated('professional_id'),
            $request->validated('reason'),
        );

        return $this->fresh($appointment);
    }

    public function confirm(ChangeAppointmentStatusRequest $request, Company $company, Appointment $appointment): AppointmentResource
    {
        return $this->changeStatus($request, $appointment, AppointmentStatus::Confirmed);
    }

    public function checkIn(ChangeAppointmentStatusRequest $request, Company $company, Appointment $appointment): AppointmentResource
    {
        return $this->changeStatus($request, $appointment, AppointmentStatus::CheckedIn);
    }

    public function complete(ChangeAppointmentStatusRequest $request, Company $company, Appointment $appointment): AppointmentResource
    {
        return $this->changeStatus($request, $appointment, AppointmentStatus::Completed);
    }

    public function noShow(ChangeAppointmentStatusRequest $request, Company $company, Appointment $appointment): AppointmentResource
    {
        return $this->changeStatus($request, $appointment, AppointmentStatus::NoShow);
    }

    public function cancel(ChangeAppointmentStatusRequest $request, Company $company, Appointment $appointment): AppointmentResource
    {
        return $this->changeStatus($request, $appointment, AppointmentStatus::Cancelled);
    }

    private function changeStatus(ChangeAppointmentStatusRequest $request, Appointment $appointment, AppointmentStatus $target): AppointmentResource
    {
        $appointment = $this->appointments->transition($appointment, $request->user(), $target, $request->validated('reason'));

        return $this->fresh($appointment);
    }

    private function fresh(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->refresh()->load([...self::WITH, 'events.actor:id,name']));
    }
}
