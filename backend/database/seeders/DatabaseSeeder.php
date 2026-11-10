<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Local development data only. Every seeded user has the password "password".
 * Two companies let the tenant isolation be checked by hand; the owner belongs to both.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('DatabaseSeeder skipped in production.');

            return;
        }

        $owner = User::factory()->create(['name' => 'Ana Proprietária', 'email' => 'owner@agendapro.test']);
        $admin = User::factory()->create(['name' => 'Bruno Administrador', 'email' => 'admin@agendapro.test']);
        $receptionist = User::factory()->create(['name' => 'Carla Recepção', 'email' => 'recepcao@agendapro.test']);
        $professional = User::factory()->create(['name' => 'Diego Profissional', 'email' => 'profissional@agendapro.test']);
        $outsider = User::factory()->create(['name' => 'Eva Outra Empresa', 'email' => 'outra@agendapro.test']);

        $studio = Company::factory()->create(['name' => 'Studio Aurora', 'slug' => 'studio-aurora']);
        $studio->members()->createMany([
            ['user_id' => $owner->id, 'role' => CompanyRole::Owner],
            ['user_id' => $admin->id, 'role' => CompanyRole::Admin],
            ['user_id' => $receptionist->id, 'role' => CompanyRole::Receptionist],
            ['user_id' => $professional->id, 'role' => CompanyRole::Professional],
        ]);

        $this->callWith(DemoScheduleSeeder::class, ['company' => $studio, 'actor' => $receptionist]);

        $clinic = Company::factory()->create(['name' => 'Clínica Horizonte', 'slug' => 'clinica-horizonte']);
        $clinic->members()->createMany([
            ['user_id' => $outsider->id, 'role' => CompanyRole::Owner],
            ['user_id' => $owner->id, 'role' => CompanyRole::Professional],
        ]);
    }
}
