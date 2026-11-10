<?php

namespace Tests\Feature\Companies;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    /** @var array<string, CompanyMember> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        foreach (CompanyRole::cases() as $role) {
            $this->members[$role->value] = CompanyMember::factory()->for($this->company)->role($role)->create();
        }
    }

    private function actAs(CompanyRole $role): void
    {
        $this->actingAs($this->members[$role->value]->user);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/companies/{$this->company->id}{$suffix}";
    }

    /** @return array<string, array{CompanyRole, bool}> */
    public static function managementMatrix(): array
    {
        return [
            'owner' => [CompanyRole::Owner, true],
            'admin' => [CompanyRole::Admin, true],
            'receptionist' => [CompanyRole::Receptionist, false],
            'professional' => [CompanyRole::Professional, false],
        ];
    }

    #[DataProvider('managementMatrix')]
    public function test_every_member_can_view_the_company(CompanyRole $role, bool $_canManage): void
    {
        $this->actAs($role);

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.membership.role', $role->value);
    }

    #[DataProvider('managementMatrix')]
    public function test_only_owner_and_admin_can_update_the_company(CompanyRole $role, bool $allowed): void
    {
        $this->actAs($role);

        $response = $this->patchJson($this->url(), ['name' => 'Novo nome']);

        $allowed ? $response->assertOk() : $response->assertForbidden();
        $this->assertSame($allowed, $this->company->fresh()->name === 'Novo nome');
    }

    #[DataProvider('managementMatrix')]
    public function test_only_owner_and_admin_can_list_members(CompanyRole $role, bool $allowed): void
    {
        $this->actAs($role);

        $response = $this->getJson($this->url('/members'));

        $allowed ? $response->assertOk()->assertJsonCount(4, 'data') : $response->assertForbidden();
    }

    #[DataProvider('managementMatrix')]
    public function test_only_owner_and_admin_can_change_roles(CompanyRole $role, bool $allowed): void
    {
        $this->actAs($role);
        $target = $this->members['professional'];

        $response = $this->patchJson($this->url("/members/{$target->id}"), ['role' => 'receptionist']);

        $allowed ? $response->assertOk()->assertJsonPath('data.role', 'receptionist') : $response->assertForbidden();
    }

    #[DataProvider('managementMatrix')]
    public function test_only_owner_and_admin_can_remove_members(CompanyRole $role, bool $allowed): void
    {
        $this->actAs($role);
        $target = $this->members['professional'];

        $response = $this->deleteJson($this->url("/members/{$target->id}"));

        $allowed ? $response->assertNoContent() : $response->assertForbidden();
        $this->assertSame(! $allowed, CompanyMember::whereKey($target->id)->exists());
    }

    public function test_admin_cannot_grant_ownership(): void
    {
        $this->actAs(CompanyRole::Admin);

        $this->patchJson($this->url("/members/{$this->members['receptionist']->id}"), ['role' => 'owner'])
            ->assertForbidden();
    }

    public function test_admin_cannot_change_or_remove_an_owner(): void
    {
        $this->actAs(CompanyRole::Admin);
        $owner = $this->members['owner'];

        $this->patchJson($this->url("/members/{$owner->id}"), ['role' => 'professional'])->assertForbidden();
        $this->deleteJson($this->url("/members/{$owner->id}"))->assertForbidden();

        $this->assertSame(CompanyRole::Owner, $owner->fresh()->role);
    }

    public function test_owner_can_grant_ownership(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->patchJson($this->url("/members/{$this->members['admin']->id}"), ['role' => 'owner'])
            ->assertOk()
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_the_last_owner_cannot_be_demoted_or_removed(): void
    {
        $this->actAs(CompanyRole::Owner);
        $owner = $this->members['owner'];

        $this->patchJson($this->url("/members/{$owner->id}"), ['role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->deleteJson($this->url("/members/{$owner->id}"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');

        $this->assertSame(CompanyRole::Owner, $owner->fresh()->role);
    }

    public function test_an_owner_can_step_down_when_another_owner_exists(): void
    {
        $secondOwner = CompanyMember::factory()->for($this->company)->role(CompanyRole::Owner)->create();
        $this->actAs(CompanyRole::Owner);

        $this->patchJson($this->url("/members/{$this->members['owner']->id}"), ['role' => 'admin'])->assertOk();

        $this->assertSame(CompanyRole::Owner, $secondOwner->fresh()->role);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->patchJson($this->url("/members/{$this->members['admin']->id}"), ['role' => 'superuser'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_company_update_validates_input(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->patchJson($this->url(), ['name' => '', 'timezone' => 'Mars/Olympus'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'timezone']);
    }

    public function test_role_permissions_are_defined_in_one_place(): void
    {
        $this->assertTrue(CompanyRole::Owner->canAdministrate(CompanyRole::Owner, CompanyRole::Admin));
        $this->assertTrue(CompanyRole::Admin->canAdministrate(CompanyRole::Professional, CompanyRole::Admin));
        $this->assertFalse(CompanyRole::Admin->canAdministrate(CompanyRole::Owner));
        $this->assertFalse(CompanyRole::Admin->canAdministrate(CompanyRole::Professional, CompanyRole::Owner));
        $this->assertFalse(CompanyRole::Receptionist->canAdministrate(CompanyRole::Professional));
        $this->assertFalse(CompanyRole::Professional->canManageCompany());
    }

    public function test_a_user_never_gets_permissions_from_another_companys_role(): void
    {
        // Owner elsewhere, professional here: must not manage this company.
        $user = $this->members['professional']->user;
        Company::factory()->withMember($user, CompanyRole::Owner)->create();
        $this->actingAs($user);

        $this->patchJson($this->url(), ['name' => 'Tentativa'])->assertForbidden();
        $this->getJson($this->url('/members'))->assertForbidden();
    }
}
