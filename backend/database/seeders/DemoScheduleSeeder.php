<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Appointments\Enums\AppointmentEventType;
use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Availability\Models\WorkingHour;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEVELOPMENT ONLY. Fills one company with a plausible operating history so screens
 * like the dashboard can be exercised. Never runs in production (see DatabaseSeeder).
 *
 * Appointments are inserted directly (past dates cannot be booked through the API) on an
 * hourly grid per professional, so they never overlap.
 */
class DemoScheduleSeeder extends Seeder
{
    public function run(Company $company, User $actor): void
    {
        mt_srand(20261109);
        $tz = $company->timezone;

        foreach (range(1, 6) as $weekday) {
            $this->hours($company, $weekday, '09:00', '12:00');
            $this->hours($company, $weekday, '13:00', $weekday === 6 ? '16:00' : '19:00');
        }

        $services = collect([
            ['Corte feminino', 'Cabelo', 12000, 60, 15, '#8B5CF6'],
            ['Corte masculino', 'Cabelo', 6000, 30, 10, '#7DD3FC'],
            ['Coloração', 'Cabelo', 22000, 120, 15, '#FDA4AF'],
            ['Barba', 'Barba', 4500, 30, 5, '#FCD34D'],
            ['Manicure', 'Estética', 5000, 45, 10, '#6EE7B7'],
        ])->map(fn (array $s) => Service::factory()->for($company)->create([
            'name' => $s[0], 'category' => $s[1], 'price_cents' => $s[2],
            'duration_minutes' => $s[3], 'buffer_minutes' => $s[4], 'color' => $s[5],
        ]));

        $professionals = collect([
            ['Marina Lopes', 'Cabeleireira', '#8B5CF6', [0, 2]],
            ['Rafael Dias', 'Barbeiro', '#7DD3FC', [1, 3]],
            ['Lívia Prado', 'Manicure', '#6EE7B7', [4]],
        ])->map(function (array $p) use ($company, $services) {
            $professional = Professional::factory()->for($company)->create(['name' => $p[0], 'title' => $p[1], 'color' => $p[2]]);
            $professional->syncServices(collect($p[3])->map(fn (int $i) => $services[$i]->id)->all());

            return [$professional, collect($p[3])->map(fn (int $i) => $services[$i])];
        });

        $customers = Customer::factory()->for($company)->count(28)->create();

        $today = CarbonImmutable::now($tz)->startOfDay();
        $now = CarbonImmutable::now();

        for ($day = -62; $day <= 10; $day++) {
            $date = $today->addDays($day);
            if ($date->dayOfWeek === CarbonImmutable::SUNDAY) {
                continue;
            }

            foreach ($professionals as [$professional, $offered]) {
                $busyUntil = null;

                foreach ([9, 10, 11, 14, 15, 16, 17] as $hour) {
                    if (mt_rand(1, 100) > 55) {
                        continue;
                    }

                    $service = $offered[mt_rand(0, $offered->count() - 1)];
                    $startsAt = $date->setTime($hour, 0)->utc();
                    if ($hour === 11 && $service->duration_minutes > 60) {
                        continue; // would run into the lunch break
                    }
                    if ($busyUntil !== null && $startsAt->lessThan($busyUntil)) {
                        continue; // previous appointment (with buffer) still running
                    }
                    $busyUntil = $startsAt->addMinutes($service->duration_minutes + $service->buffer_minutes);

                    $status = $this->statusFor($startsAt, $now);
                    $endsAt = $startsAt->addMinutes($service->duration_minutes);

                    $appointment = Appointment::create([
                        'company_id' => $company->id,
                        'customer_id' => $customers[mt_rand(0, $customers->count() - 1)]->id,
                        'professional_id' => $professional->id,
                        'service_id' => $service->id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'occupied_until' => $endsAt->addMinutes($service->buffer_minutes),
                        'status' => $status,
                        'service_name' => $service->name,
                        'service_price_cents' => $service->price_cents,
                        'service_duration_minutes' => $service->duration_minutes,
                        'service_buffer_minutes' => $service->buffer_minutes,
                        'created_by' => $actor->id,
                        'cancelled_at' => $status === AppointmentStatus::Cancelled ? $startsAt->subDay() : null,
                        'cancelled_by' => $status === AppointmentStatus::Cancelled ? $actor->id : null,
                        'cancellation_reason' => $status === AppointmentStatus::Cancelled ? 'Cliente pediu para desmarcar' : null,
                        'completed_at' => $status === AppointmentStatus::Completed ? $endsAt : null,
                        // Booked some days before the appointment, but never in the future.
                        'created_at' => $startsAt->subDays(mt_rand(1, 10))->min($now->subMinutes(mt_rand(10, 600))),
                    ]);

                    $this->events($appointment, $actor, $status);
                }
            }
        }
    }

    private function hours(Company $company, int $weekday, string $start, string $end): void
    {
        WorkingHour::create(['company_id' => $company->id, 'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end]);
    }

    private function statusFor(CarbonImmutable $startsAt, CarbonImmutable $now): AppointmentStatus
    {
        $roll = mt_rand(1, 100);

        if ($startsAt->lessThan($now->subHours(2))) {
            return match (true) {
                $roll <= 80 => AppointmentStatus::Completed,
                $roll <= 90 => AppointmentStatus::NoShow,
                default => AppointmentStatus::Cancelled,
            };
        }

        return match (true) {
            $roll <= 45 => AppointmentStatus::Confirmed,
            $roll <= 92 => AppointmentStatus::Scheduled,
            default => AppointmentStatus::Cancelled,
        };
    }

    private function events(Appointment $appointment, User $actor, AppointmentStatus $status): void
    {
        $at = $appointment->created_at;
        $appointment->events()->create([
            'company_id' => $appointment->company_id, 'type' => AppointmentEventType::Created,
            'to_status' => AppointmentStatus::Scheduled, 'actor_id' => $actor->id, 'created_at' => $at,
        ]);

        if ($status !== AppointmentStatus::Scheduled) {
            $appointment->events()->create([
                'company_id' => $appointment->company_id, 'type' => AppointmentEventType::forStatus($status),
                'from_status' => AppointmentStatus::Scheduled, 'to_status' => $status, 'actor_id' => $actor->id,
                'reason' => $appointment->cancellation_reason,
                'created_at' => ($status === AppointmentStatus::Completed ? $appointment->ends_at : $at->addHours(2))->min(CarbonImmutable::now()),
            ]);
        }
    }
}
