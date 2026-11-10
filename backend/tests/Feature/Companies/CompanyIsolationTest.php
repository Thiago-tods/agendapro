<?php

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private Company $companyA;

    private Company $companyB;

    private CompanyMember $memberOfB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->companyA = Company::factory()->withMember($this->userA, CompanyRole::Owner)->create();

        $this->companyB = Company::factory()->create();
        $this->memberOfB = CompanyMember::factory()->for($this->companyB)->role(CompanyRole::Professional)->create();

        $this->actingAs($this->userA);
    }

    public function test_guests_cannot_reach_company_routes(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/companies')->assertUnauthorized();
        $this->getJson("/api/v1/companies/{$this->companyA->id}")->assertUnauthorized();
    }

    public function test_company_list_only_contains_the_users_own_companies(): void
    {
        $this->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->companyA->id)
            ->assertJsonPath('data.0.membership.role', 'owner');
    }

    public function test_a_user_in_several_companies_sees_each_with_its_own_role(): void
    {
        $this->companyB->members()->create(['user_id' => $this->userA->id, 'role' => CompanyRole::Receptionist]);

        $roles = collect($this->getJson('/api/v1/companies')->assertOk()->json('data'))
            ->mapWithKeys(fn (array $c): array => [$c['id'] => $c['membership']['role']]);

        $this->assertSame('owner', $roles[$this->companyA->id]);
        $this->assertSame('receptionist', $roles[$this->companyB->id]);
    }

    public function test_another_companys_data_is_not_found_rather_than_forbidden(): void
    {
        $id = $this->companyB->id;

        $this->getJson("/api/v1/companies/{$id}")->assertNotFound();
        $this->patchJson("/api/v1/companies/{$id}", ['name' => 'Invadida'])->assertNotFound();
        $this->getJson("/api/v1/companies/{$id}/members")->assertNotFound();
        $this->patchJson("/api/v1/companies/{$id}/members/{$this->memberOfB->id}", ['role' => 'admin'])->assertNotFound();
        $this->deleteJson("/api/v1/companies/{$id}/members/{$this->memberOfB->id}")->assertNotFound();

        $this->assertNotSame('Invadida', $this->companyB->fresh()->name);
        $this->assertSame(CompanyRole::Professional, $this->memberOfB->fresh()->role);
    }

    public function test_a_member_of_another_company_cannot_be_reached_through_the_users_own_company(): void
    {
        $url = "/api/v1/companies/{$this->companyA->id}/members/{$this->memberOfB->id}";

        $this->patchJson($url, ['role' => 'admin'])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();

        $this->assertDatabaseHas('company_members', ['id' => $this->memberOfB->id, 'role' => 'professional']);
    }

    public function test_members_list_never_includes_other_companies_members(): void
    {
        $ids = collect($this->getJson("/api/v1/companies/{$this->companyA->id}/members")->assertOk()->json('data'))
            ->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertNotContains($this->memberOfB->id, $ids);
    }

    public function test_company_id_in_the_payload_is_ignored(): void
    {
        $this->patchJson("/api/v1/companies/{$this->companyA->id}", [
            'name' => 'Nome novo',
            'id' => $this->companyB->id,
            'company_id' => $this->companyB->id,
        ])->assertOk()->assertJsonPath('data.id', $this->companyA->id);

        $this->assertSame('Nome novo', $this->companyA->fresh()->name);
        $this->assertNotSame('Nome novo', $this->companyB->fresh()->name);
    }

    public function test_unknown_and_malformed_company_ids_return_404(): void
    {
        $this->getJson('/api/v1/companies/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/v1/companies/not-a-uuid')->assertNotFound();
    }

    public function test_removing_a_membership_immediately_revokes_access(): void
    {
        $this->companyA->members()->create(['user_id' => User::factory()->create()->id, 'role' => CompanyRole::Owner]);
        $this->userA->memberships()->delete();

        $this->getJson("/api/v1/companies/{$this->companyA->id}")->assertNotFound();
    }

    public function test_database_rejects_duplicate_memberships(): void
    {
        $this->expectException(QueryException::class);

        $this->companyA->members()->create(['user_id' => $this->userA->id, 'role' => CompanyRole::Admin]);
    }

    public function test_database_rejects_unknown_roles(): void
    {
        $this->expectException(QueryException::class);

        DB::table('company_members')->insert([
            'id' => Str::uuid()->toString(),
            'company_id' => $this->companyA->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'superuser',
        ]);
    }
}
