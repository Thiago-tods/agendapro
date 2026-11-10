<?php

namespace Tests\Feature\Customers;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsCompanyMember;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use ActsAsCompanyMember, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCompanies();
    }

    // --- Success -----------------------------------------------------------------------

    public function test_receptionist_creates_a_customer_with_normalized_contact_data(): void
    {
        $this->actAs(CompanyRole::Receptionist);

        $this->postJson($this->companyUrl('/customers'), [
            'name' => 'Maria Oliveira',
            'email' => '  Maria@Email.COM ',
            'phone' => '(11) 98765-4321',
            'birth_date' => '1990-04-12',
            'notes' => 'Prefere horários pela manhã.',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Maria Oliveira')
            ->assertJsonPath('data.email', 'maria@email.com')
            ->assertJsonPath('data.phone', '11987654321')
            ->assertJsonPath('data.birth_date', '1990-04-12')
            ->assertJsonPath('data.is_archived', false);

        $this->assertDatabaseHas('customers', ['company_id' => $this->company->id, 'phone' => '11987654321']);
    }

    public function test_update_changes_only_the_fields_sent(): void
    {
        $this->actAs(CompanyRole::Admin);
        $customer = Customer::factory()->for($this->company)->create(['name' => 'Antes', 'email' => 'a@b.com']);

        $this->patchJson($this->companyUrl("/customers/{$customer->id}"), ['name' => 'Depois'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Depois')
            ->assertJsonPath('data.email', 'a@b.com');
    }

    public function test_search_matches_name_email_and_phone_digits(): void
    {
        $this->actAs(CompanyRole::Professional);
        Customer::factory()->for($this->company)->create(['name' => 'João Silva', 'email' => 'joao@x.com', 'phone' => '11911112222']);
        Customer::factory()->for($this->company)->create(['name' => 'Ana Costa', 'email' => 'ana@y.com', 'phone' => '21933334444']);

        $this->getJson($this->companyUrl('/customers?search=silva'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'João Silva');
        $this->getJson($this->companyUrl('/customers?search=ANA@Y'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ana Costa');
        $this->getJson($this->companyUrl('/customers?search='.urlencode('(21) 93333')))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ana Costa');
        $this->getJson($this->companyUrl('/customers?search=100%25'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_status_filter_and_pagination(): void
    {
        $this->actAs(CompanyRole::Owner);
        Customer::factory()->for($this->company)->count(3)->create();
        Customer::factory()->for($this->company)->archived()->count(2)->create();

        $this->getJson($this->companyUrl('/customers'))->assertJsonCount(3, 'data');
        $this->getJson($this->companyUrl('/customers?status=archived'))->assertJsonCount(2, 'data');
        $this->getJson($this->companyUrl('/customers?status=all&per_page=4'))
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_archive_and_restore(): void
    {
        $this->actAs(CompanyRole::Receptionist);
        $customer = Customer::factory()->for($this->company)->create();

        $this->postJson($this->companyUrl("/customers/{$customer->id}/archive"))->assertOk()->assertJsonPath('data.is_archived', true);
        $this->getJson($this->companyUrl('/customers'))->assertJsonCount(0, 'data');

        $this->postJson($this->companyUrl("/customers/{$customer->id}/restore"))->assertOk()->assertJsonPath('data.is_archived', false);
        $this->getJson($this->companyUrl('/customers'))->assertJsonCount(1, 'data');
    }

    public function test_owner_deletes_a_customer_without_history(): void
    {
        $this->actAs(CompanyRole::Owner);
        $customer = Customer::factory()->for($this->company)->create();

        $this->deleteJson($this->companyUrl("/customers/{$customer->id}"))->assertNoContent();
        $this->assertModelMissing($customer);
    }

    // --- Validation --------------------------------------------------------------------

    public function test_create_validates_input(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/customers'), [
            'email' => 'invalido',
            'phone' => '123',
            'birth_date' => now()->addDay()->format('Y-m-d'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'birth_date']);
    }

    public function test_validation_messages_are_in_portuguese(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/customers'), ['name' => 'A', 'email' => 'invalido'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'O campo nome deve ter pelo menos 2 caracteres.')
            ->assertJsonPath('errors.email.0', 'O campo e-mail deve ser um endereço de e-mail válido.')
            ->assertJsonPath('message', 'O campo nome deve ter pelo menos 2 caracteres. (e mais 1 erro)');
    }

    public function test_patch_cannot_blank_the_name(): void
    {
        $this->actAs(CompanyRole::Owner);
        $customer = Customer::factory()->for($this->company)->create();

        $this->patchJson($this->companyUrl("/customers/{$customer->id}"), ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_index_rejects_invalid_filters(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->getJson($this->companyUrl('/customers?status=deleted&per_page=1000'))
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'per_page']);
    }

    // --- Authorization -----------------------------------------------------------------

    #[DataProvider('allRoles')]
    public function test_every_member_can_list_and_view(CompanyRole $role): void
    {
        $this->actAs($role);
        $customer = Customer::factory()->for($this->company)->create();

        $this->getJson($this->companyUrl('/customers'))->assertOk();
        $this->getJson($this->companyUrl("/customers/{$customer->id}"))->assertOk();
    }

    public function test_professional_cannot_create_edit_or_archive(): void
    {
        $this->actAs(CompanyRole::Professional);
        $customer = Customer::factory()->for($this->company)->create();

        $this->postJson($this->companyUrl('/customers'), ['name' => 'Novo'])->assertForbidden();
        $this->patchJson($this->companyUrl("/customers/{$customer->id}"), ['name' => 'X'])->assertForbidden();
        $this->postJson($this->companyUrl("/customers/{$customer->id}/archive"))->assertForbidden();
    }

    public function test_receptionist_cannot_delete_permanently(): void
    {
        $this->actAs(CompanyRole::Receptionist);
        $customer = Customer::factory()->for($this->company)->create();

        $this->deleteJson($this->companyUrl("/customers/{$customer->id}"))->assertForbidden();
        $this->assertModelExists($customer);
    }

    // --- Isolation ---------------------------------------------------------------------

    public function test_list_never_includes_other_companies_customers(): void
    {
        $this->actAs(CompanyRole::Owner);
        Customer::factory()->for($this->company)->create(['name' => 'Meu cliente']);
        Customer::factory()->for($this->otherCompany)->create(['name' => 'Cliente alheio']);

        $this->getJson($this->companyUrl('/customers?status=all'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Meu cliente');
    }

    public function test_another_companys_customer_is_not_found_through_own_company(): void
    {
        $this->actAs(CompanyRole::Owner);
        $foreign = Customer::factory()->for($this->otherCompany)->create(['name' => 'Alheio']);
        $base = $this->companyUrl("/customers/{$foreign->id}");

        $this->getJson($base)->assertNotFound();
        $this->patchJson($base, ['name' => 'Invadido'])->assertNotFound();
        $this->postJson("{$base}/archive")->assertNotFound();
        $this->deleteJson($base)->assertNotFound();

        $this->assertSame('Alheio', $foreign->fresh()->name);
        $this->assertNull($foreign->fresh()->archived_at);
    }

    public function test_another_companys_routes_are_not_found(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->getJson($this->companyUrl('/customers', $this->otherCompany))->assertNotFound();
        $this->postJson($this->companyUrl('/customers', $this->otherCompany), ['name' => 'X'])->assertNotFound();
    }

    public function test_company_id_in_payload_is_ignored(): void
    {
        $this->actAs(CompanyRole::Owner);

        $id = $this->postJson($this->companyUrl('/customers'), [
            'name' => 'Cliente',
            'company_id' => $this->otherCompany->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame($this->company->id, Customer::find($id)->company_id);
    }
}
