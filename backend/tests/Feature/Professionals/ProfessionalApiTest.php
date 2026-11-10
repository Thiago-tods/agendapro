<?php

namespace Tests\Feature\Professionals;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsCompanyMember;
use Tests\TestCase;

class ProfessionalApiTest extends TestCase
{
    use ActsAsCompanyMember, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCompanies();
    }

    // --- Success -----------------------------------------------------------------------

    public function test_admin_creates_a_professional_with_services(): void
    {
        $this->actAs(CompanyRole::Admin);
        $services = Service::factory()->for($this->company)->count(2)->create();

        $this->postJson($this->companyUrl('/professionals'), [
            'name' => 'Paula Souza',
            'title' => 'Cabeleireira',
            'bio' => 'Especialista em coloração.',
            'email' => 'Paula@Studio.com',
            'phone' => '(11) 91234-5678',
            'color' => '#8b5cf6',
            'service_ids' => $services->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Paula Souza')
            ->assertJsonPath('data.email', 'paula@studio.com')
            ->assertJsonPath('data.color', '#8B5CF6')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonCount(2, 'data.services');
    }

    public function test_deactivate_and_reactivate(): void
    {
        $this->actAs(CompanyRole::Owner);
        $professional = Professional::factory()->for($this->company)->create();

        $this->patchJson($this->companyUrl("/professionals/{$professional->id}"), ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson($this->companyUrl("/professionals/{$professional->id}"), ['is_active' => true])
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_service_ids_replace_associations_only_when_sent(): void
    {
        $this->actAs(CompanyRole::Owner);
        [$a, $b] = Service::factory()->for($this->company)->count(2)->create()->all();
        $professional = Professional::factory()->for($this->company)->create();
        $professional->syncServices([$a->id]);
        $url = $this->companyUrl("/professionals/{$professional->id}");

        $this->patchJson($url, ['title' => 'Barbeiro'])->assertJsonCount(1, 'data.services');
        $this->patchJson($url, ['service_ids' => [$b->id]])->assertJsonPath('data.services.0.id', $b->id);
        $this->patchJson($url, ['service_ids' => []])->assertJsonCount(0, 'data.services');
    }

    public function test_filters_by_status_search_and_service(): void
    {
        $this->actAs(CompanyRole::Receptionist);
        $service = Service::factory()->for($this->company)->create();
        $carla = Professional::factory()->for($this->company)->create(['name' => 'Carla']);
        $carla->syncServices([$service->id]);
        Professional::factory()->for($this->company)->inactive()->create(['name' => 'Bruno']);

        $this->getJson($this->companyUrl('/professionals'))->assertJsonCount(2, 'data');
        $this->getJson($this->companyUrl('/professionals?status=active'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Carla');
        $this->getJson($this->companyUrl('/professionals?status=inactive'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bruno');
        $this->getJson($this->companyUrl('/professionals?search=carl'))->assertJsonCount(1, 'data');
        $this->getJson($this->companyUrl("/professionals?service_id={$service->id}"))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.services_count', 1);
    }

    public function test_owner_deletes_a_professional_and_its_associations(): void
    {
        $this->actAs(CompanyRole::Owner);
        $professional = Professional::factory()->for($this->company)->create();
        $professional->syncServices([Service::factory()->for($this->company)->create()->id]);

        $this->deleteJson($this->companyUrl("/professionals/{$professional->id}"))->assertNoContent();

        $this->assertModelMissing($professional);
        $this->assertDatabaseMissing('professional_service', ['professional_id' => $professional->id]);
    }

    // --- Validation --------------------------------------------------------------------

    public function test_create_validates_input(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/professionals'), [
            'color' => 'roxo',
            'email' => 'x',
            'is_active' => 'talvez',
            'service_ids' => ['nao-e-uuid'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'color', 'email', 'is_active', 'service_ids.0']);
    }

    // --- Authorization -----------------------------------------------------------------

    #[DataProvider('allRoles')]
    public function test_every_member_can_list_and_view(CompanyRole $role): void
    {
        $this->actAs($role);
        $professional = Professional::factory()->for($this->company)->create();

        $this->getJson($this->companyUrl('/professionals'))->assertOk();
        $this->getJson($this->companyUrl("/professionals/{$professional->id}"))->assertOk();
    }

    /** @return array<string, array{CompanyRole}> */
    public static function nonManagers(): array
    {
        return ['receptionist' => [CompanyRole::Receptionist], 'professional' => [CompanyRole::Professional]];
    }

    #[DataProvider('nonManagers')]
    public function test_only_owner_and_admin_can_write(CompanyRole $role): void
    {
        $this->actAs($role);
        $professional = Professional::factory()->for($this->company)->create();
        $url = $this->companyUrl("/professionals/{$professional->id}");

        $this->postJson($this->companyUrl('/professionals'), ['name' => 'X', 'color' => '#FFFFFF'])->assertForbidden();
        $this->patchJson($url, ['is_active' => false])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();

        $this->assertTrue($professional->fresh()->is_active);
    }

    // --- Isolation ---------------------------------------------------------------------

    public function test_another_companys_professional_is_not_found(): void
    {
        $this->actAs(CompanyRole::Owner);
        $foreign = Professional::factory()->for($this->otherCompany)->create();
        $url = $this->companyUrl("/professionals/{$foreign->id}");

        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, ['is_active' => false])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->getJson($this->companyUrl('/professionals', $this->otherCompany))->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_cannot_associate_a_service_from_another_company(): void
    {
        $this->actAs(CompanyRole::Owner);
        $foreignService = Service::factory()->for($this->otherCompany)->create();

        $this->postJson($this->companyUrl('/professionals'), [
            'name' => 'Paula',
            'color' => '#8B5CF6',
            'service_ids' => [$foreignService->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('service_ids.0');

        $this->assertDatabaseCount('professionals', 0);
    }

    public function test_database_refuses_cross_company_associations(): void
    {
        $professional = Professional::factory()->for($this->company)->create();
        $foreignService = Service::factory()->for($this->otherCompany)->create();

        $this->expectException(QueryException::class);

        DB::table('professional_service')->insert([
            'company_id' => $this->company->id,
            'professional_id' => $professional->id,
            'service_id' => $foreignService->id,
        ]);
    }

    public function test_list_never_includes_other_companies_professionals(): void
    {
        $this->actAs(CompanyRole::Owner);
        Professional::factory()->for($this->company)->create();
        Professional::factory()->for($this->otherCompany)->count(2)->create();

        $this->getJson($this->companyUrl('/professionals'))->assertJsonCount(1, 'data');
    }
}
