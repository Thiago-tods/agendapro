<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Appointments\Http\Resources\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentEvent;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Support\CurrentCompany;
use App\Modules\Dashboard\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController
{
    public function __invoke(Company $company, CurrentCompany $current, DashboardService $dashboard): JsonResponse
    {
        Gate::authorize('view', $company);

        $data = $dashboard->build($company, $current->membership()->role);
        $tz = $company->timezone;

        $data['upcoming'] = AppointmentResource::collection($data['upcoming'])->resolve();
        $data['recent_activity'] = $data['recent_activity']->map(fn (AppointmentEvent $event): array => [
            'id' => $event->id,
            'type' => $event->type->value,
            'label' => $event->type->label(),
            'reason' => $event->reason,
            'actor' => $event->actor?->name,
            'created_at' => $event->created_at->toIso8601ZuluString(),
            'appointment' => [
                'id' => $event->appointment->id,
                'customer' => $event->appointment->customer->name,
                'service' => $event->appointment->service_name,
                'local_start' => $event->appointment->starts_at->setTimezone($tz)->format('Y-m-d H:i'),
            ],
        ])->values()->all();

        return response()->json(['data' => $data]);
    }
}
