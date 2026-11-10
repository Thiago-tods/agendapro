<?php

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsCompanyMember;
use Tests\TestCase;

class CompanyMemberStoreTest extends TestCase
{
    use ActsAsCompanyMember, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCompanies();
    }

    public function test_admin_adds_a_new_person_with_an_initial_password(): void
    {
        $this->actAs(CompanyRole::Admin);

        $this->postJson($this->companyUrl('/members'), [
            'email' => 'Nova@Equipe.com',
            'name' => 'Nova Pessoa',
            'password' => 'inicial123',
            'role' => 'receptionist',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'receptionist')
            ->assertJsonPath('data.user.email', 'nova@equipe.com');

        $user = User::where('email', 'nova@equipe.com')->firstOrFail();
        $this->assertTrue(Hash::check('inicial123', $user->password));
        $this->assertSame(CompanyRole::Receptionist, $user->roleIn($this->company));
    }

    public function test_existing_account_is_attached_without_changing_it(): void
    {
        $this->actAs(CompanyRole::Owner);
        $existing = User::factory()->create(['email' => 'ja@existe.com', 'name' => 'Original']);
        $this->otherCompany->members()->create(['user_id' => $existing->id, 'role' => CompanyRole::Owner]);

        $this->postJson($this->companyUrl('/members'), ['email' => 'ja@existe.com', 'role' => 'professional'])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Original');

        $this->postJson($this->companyUrl('/members'), ['email' => 'ja@existe.com', 'role' => 'admin', 'password' => 'outra1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $this->actAs(CompanyRole::Owner);
        $existing = User::factory()->create(['email' => 'dup@x.com']);
        $this->company->members()->create(['user_id' => $existing->id, 'role' => CompanyRole::Professional]);

        $this->postJson($this->companyUrl('/members'), ['email' => 'dup@x.com', 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Esta pessoa já faz parte da equipe.');
    }

    public function test_only_owners_can_add_owners(): void
    {
        $this->actAs(CompanyRole::Admin);

        $this->postJson($this->companyUrl('/members'), [
            'email' => 'dono@x.com', 'name' => 'Dono', 'password' => 'senha1234', 'role' => 'owner',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'dono@x.com']);
    }

    public function test_receptionists_cannot_add_members(): void
    {
        $this->actAs(CompanyRole::Receptionist);

        $this->postJson($this->companyUrl('/members'), [
            'email' => 'x@x.com', 'name' => 'X X', 'password' => 'senha1234', 'role' => 'professional',
        ])->assertForbidden();
    }

    public function test_members_of_another_company_get_404(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/members', $this->otherCompany), [
            'email' => 'x@x.com', 'name' => 'X X', 'password' => 'senha1234', 'role' => 'professional',
        ])->assertNotFound();
    }
}
