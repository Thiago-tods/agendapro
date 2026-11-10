<?php

use App\Modules\Appointments\Http\Controllers\AppointmentController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\ProfileController;
use App\Modules\Availability\Http\Controllers\AvailabilityController;
use App\Modules\Availability\Http\Controllers\CalendarAvailabilityController;
use App\Modules\Companies\Http\Controllers\CompanyController;
use App\Modules\Companies\Http\Controllers\CompanyMemberController;
use App\Modules\Customers\Http\Controllers\CustomerController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Professionals\Http\Controllers\ProfessionalController;
use App\Modules\Services\Http\Controllers\ServiceController;
use App\Modules\System\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php).

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

Route::middleware('auth:sanctum')->name('api.v1.')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::patch('/auth/profile', [ProfileController::class, 'update'])->name('auth.profile');
    Route::put('/auth/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('auth.password');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');

    // Tenant-scoped routes: the company comes from the URL and is checked against the
    // user's memberships by company.member. Company-owned resources go inside this group.
    Route::prefix('/companies/{company}')
        ->middleware('company.member')
        ->whereUuid(['company', 'member', 'customer', 'professional', 'service', 'appointment', 'availabilityException', 'blockedPeriod'])
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/', [CompanyController::class, 'show'])->name('companies.show');
            Route::patch('/', [CompanyController::class, 'update'])->name('companies.update');

            Route::get('/members', [CompanyMemberController::class, 'index'])->name('companies.members.index');
            Route::post('/members', [CompanyMemberController::class, 'store'])->name('companies.members.store');
            Route::patch('/members/{member}', [CompanyMemberController::class, 'update'])->name('companies.members.update');
            Route::delete('/members/{member}', [CompanyMemberController::class, 'destroy'])->name('companies.members.destroy');

            Route::apiResource('customers', CustomerController::class);
            Route::post('/customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
            Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');

            Route::apiResource('professionals', ProfessionalController::class);
            Route::apiResource('services', ServiceController::class);

            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/calendar/availability', CalendarAvailabilityController::class)->name('calendar.availability');

            // Availability
            Route::controller(AvailabilityController::class)->group(function (): void {
                Route::get('/business-hours', 'businessHours')->name('business-hours.show');
                Route::put('/business-hours', 'updateBusinessHours')->name('business-hours.update');
                Route::get('/professionals/{professional}/working-hours', 'workingHours')->name('working-hours.show');
                Route::put('/professionals/{professional}/working-hours', 'updateWorkingHours')->name('working-hours.update');

                Route::get('/availability-exceptions', 'exceptions')->name('availability-exceptions.index');
                Route::post('/availability-exceptions', 'storeException')->name('availability-exceptions.store');
                Route::delete('/availability-exceptions/{availabilityException}', 'destroyException')->name('availability-exceptions.destroy');

                Route::get('/blocked-periods', 'blockedPeriods')->name('blocked-periods.index');
                Route::post('/blocked-periods', 'storeBlockedPeriod')->name('blocked-periods.store');
                Route::delete('/blocked-periods/{blockedPeriod}', 'destroyBlockedPeriod')->name('blocked-periods.destroy');
            });

            // Appointments (never deleted: cancellation is a status transition)
            Route::controller(AppointmentController::class)->group(function (): void {
                Route::get('/appointments', 'index')->name('appointments.index');
                Route::post('/appointments', 'store')->name('appointments.store');
                Route::get('/appointments/{appointment}', 'show')->name('appointments.show');
                Route::post('/appointments/{appointment}/reschedule', 'reschedule')->name('appointments.reschedule');
                Route::post('/appointments/{appointment}/confirm', 'confirm')->name('appointments.confirm');
                Route::post('/appointments/{appointment}/check-in', 'checkIn')->name('appointments.check-in');
                Route::post('/appointments/{appointment}/complete', 'complete')->name('appointments.complete');
                Route::post('/appointments/{appointment}/no-show', 'noShow')->name('appointments.no-show');
                Route::post('/appointments/{appointment}/cancel', 'cancel')->name('appointments.cancel');

                Route::get('/customers/{customer}/appointments', 'forCustomer')->name('customers.appointments');
            });
        });
});
