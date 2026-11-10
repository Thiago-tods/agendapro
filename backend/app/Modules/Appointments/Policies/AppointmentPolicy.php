<?php

namespace App\Modules\Appointments\Policies;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Models\Company;

/**
 * There is no delete ability: appointments are never deleted, only cancelled.
 */
class AppointmentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $user->roleIn($company) !== null;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->roleIn($appointment->company_id) !== null;
    }

    public function create(User $user, Company $company): bool
    {
        return (bool) $user->roleIn($company)?->canManageAppointments();
    }

    /** Confirm, reschedule, check in, complete, cancel, no-show. */
    public function manage(User $user, Appointment $appointment): bool
    {
        return (bool) $user->roleIn($appointment->company_id)?->canManageAppointments();
    }
}
