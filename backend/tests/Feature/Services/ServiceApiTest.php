<?php

namespace Tests\Feature\Services;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsCompanyMember;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use ActsAsCompanyMember, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCompanies();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Corte feminino',
            'description' => 'Corte com lavagem e finalização.',
            'category' => 'Cabelo',
            'price_cents' => 12000,
            'duration_minutes' => 60,
            'buffer_minutes' => 10,
            'color' => '#7dd3fc',
            ...$overrides,
        ];
    }

    // --- Success -----------------------------------------------------------------------

    public function test_owner_creates_a_service_with_professionals(): void
    {
        $this->actAs(CompanyRole::Owner);
        $professional = Professional::factory()->for($this->company)->create();

        $this->postJson($this->companyUrl('/services'), $this->validPayload(['professional_ids' => [$professional->id]]))
            ->assertCreated()
            ->assertJsonPath('data.price_cents', 12000)
            ->assertJsonPath('data.duration_minutes', 60)
            ->assertJsonPath('data.buffer_minutes', 10)
            ->assertJsonPath('data.color', '#7DD3FC')
            ->assertJsonPath('data.category', 'Cabelo')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.professionals.0.id', $professional->id);
    }

    public function test_buffer_defaults_to_zero(): void
    {
        $this->actAs(CompanyRole::Admin);

        $payload = $this->validPayload();
        unset($payload['buffer_minutes']);

        $this->postJson($this->companyUrl('/services'), $payload)->assertCreated()->assertJsonPath('data.buffer_minutes', 0);
    }

    public function test_price_is_stored_as_integer_cents(): void
    {
        $this->actAs(CompanyRole::Owner);

        $id = $this->postJson($this->companyUrl('/services'), $this->validPayload(['price_cents' => 4990]))->json('data.id');

        $this->assertSame(4990, Service::find($id)->price_cents);
    }

    public function test_deactivate_and_associations_from_the_service_side(): void
    {
        $this->actAs(CompanyRole::Owner);
        $service = Service::factory()->for($this->company)->create();
        $professional = Professional::factory()->for($this->company)->create();
        $url = $this->companyUrl("/services/{$service->id}");

        $this->patchJson($url, ['is_active' => false, 'professional_ids' => [$professional->id]])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(1, 'data.professionals');

        // Visible from the professional side too.
        $this->getJson($this->companyUrl("/professionals/{$professional->id}"))->assertJsonPath('data.services.0.id', $service->id);
    }

    public function test_filters_by_category_status_search_and_professional(): void
    {
        $this->actAs(CompanyRole::Professional);
        $professional = Professional::factory()->for($this->company)->create();
        $corte = Service::factory()->for($this->company)->create(['name' => 'Corte', 'category' => 'Cabelo']);
        $corte->syncProfessionals([$professional->id]);
        Service::factory()->for($this->company)->inactive()->create(['name' => 'Barba', 'category' => 'Barba']);

        $this->getJson($this->companyUrl('/services?category=Cabelo'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Corte');
        $this->getJson($this->companyUrl('/services?status=inactive'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Barba');
        $this->getJson($this->companyUrl('/services?search=barb'))->assertJsonCount(1, 'data');
        $this->getJson($this->companyUrl("/services?professional_id={$professional->id}"))
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.professionals_count', 1);
    }

    public function test_admin_deletes_a_service(): void
    {
        $this->actAs(CompanyRole::Admin);
        $service = Service::factory()->for($this->company)->create();

        $this->deleteJson($this->companyUrl("/services/{$service->id}"))->assertNoContent();
        $this->assertModelMissing($service);
    }

    // --- Validation --------------------------------------------------------------------

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        return [
            'negative price' => ['price_cents', -1],
            'decimal price' => ['price_cents', 49.9],
            'price as string' => ['price_cents', 'R$ 50'],
            'duration too short' => ['duration_minutes', 0],
            'duration too long' => ['duration_minutes', 725],
            'duration not multiple of 5' => ['duration_minutes', 33],
            'negative buffer' => ['buffer_minutes', -5],
            'buffer too long' => ['buffer_minutes', 245],
            'color name' => ['color', 'purple'],
            'short color' => ['color', '#FFF'],
            'empty name' => ['name', ''],
            'long category' => ['category', str_repeat('a', 61)],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_rejects_invalid_values(string $field, mixed $value): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/services'), $this->validPayload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_required_fields_on_create(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/services'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price_cents', 'duration_minutes', 'color']);
    }

    public function test_database_constraints_protect_price_and_duration(): void
    {
        $this->expectException(QueryException::class);

        Service::factory()->for($this->company)->create(['duration_minutes' => 1]);
    }

    // --- Authorization -----------------------------------------------------------------

    #[DataProvider('allRoles')]
    public function test_every_member_can_list_and_view(CompanyRole $role): void
    {
        $this->actAs($role);
        $service = Service::factory()->for($this->company)->create();

        $this->getJson($this->companyUrl('/services'))->assertOk();
        $this->getJson($this->companyUrl("/services/{$service->id}"))->assertOk();
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
        $service = Service::factory()->for($this->company)->create(['price_cents' => 5000]);
        $url = $this->companyUrl("/services/{$service->id}");

        $this->postJson($this->companyUrl('/services'), $this->validPayload())->assertForbidden();
        $this->patchJson($url, ['price_cents' => 1])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();

        $this->assertSame(5000, $service->fresh()->price_cents);
    }

    // --- Isolation ---------------------------------------------------------------------

    public function test_another_companys_service_is_not_found(): void
    {
        $this->actAs(CompanyRole::Owner);
        $foreign = Service::factory()->for($this->otherCompany)->create(['price_cents' => 5000]);
        $url = $this->companyUrl("/services/{$foreign->id}");

        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, ['price_cents' => 1])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->getJson($this->companyUrl('/services', $this->otherCompany))->assertNotFound();

        $this->assertSame(5000, $foreign->fresh()->price_cents);
    }

    public function test_cannot_associate_a_professional_from_another_company(): void
    {
        $this->actAs(CompanyRole::Owner);
        $service = Service::factory()->for($this->company)->create();
        $foreignProfessional = Professional::factory()->for($this->otherCompany)->create();

        $this->patchJson($this->companyUrl("/services/{$service->id}"), ['professional_ids' => [$foreignProfessional->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('professional_ids.0');

        $this->assertDatabaseCount('professional_service', 0);
    }

    public function test_list_never_includes_other_companies_services(): void
    {
        $this->actAs(CompanyRole::Owner);
        Service::factory()->for($this->company)->create();
        Service::factory()->for($this->otherCompany)->count(3)->create();

        $this->getJson($this->companyUrl('/services'))->assertJsonCount(1, 'data');
    }
}
