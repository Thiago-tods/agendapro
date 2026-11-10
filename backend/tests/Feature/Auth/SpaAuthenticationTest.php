<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Requests carry the SPA origin, as a browser would, so Sanctum treats them as stateful
 * (session cookie) requests.
 */
class SpaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SPA = 'http://localhost:5173';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost:5173']]);
        $this->withHeaders(['Origin' => self::SPA, 'Referer' => self::SPA.'/login']);

        $this->user = User::factory()->create(['email' => 'ana@studio.test', 'password' => 'segredo-forte']);
        Company::factory()->withMember($this->user, CompanyRole::Receptionist)->create(['name' => 'B Studio']);
        Company::factory()->withMember($this->user, CompanyRole::Owner)->create(['name' => 'A Clínica']);
        Company::factory()->create(['name' => 'Alheia']);
    }

    public function test_login_starts_a_session_and_returns_user_with_companies_and_permissions(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => ' ANA@studio.test ', 'password' => 'segredo-forte'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'ana@studio.test')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonCount(2, 'data.companies')
            ->assertJsonPath('data.companies.0.name', 'A Clínica')
            ->assertJsonPath('data.companies.0.membership.role', 'owner')
            ->assertJsonPath('data.companies.0.membership.permissions.manage_company', true)
            ->assertJsonPath('data.companies.1.membership.role', 'receptionist')
            ->assertJsonPath('data.companies.1.membership.permissions.manage_company', false)
            ->assertJsonPath('data.companies.1.membership.permissions.manage_appointments', true);

        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function test_session_is_restored_by_me(): void
    {
        $this->actingAs($this->user, 'web');

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.id', $this->user->id);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'Não autenticado.');
    }

    public function test_wrong_password_and_unknown_email_get_the_same_answer(): void
    {
        $wrong = $this->postJson('/api/v1/auth/login', ['email' => 'ana@studio.test', 'password' => 'errada'])
            ->assertUnprocessable()->json('errors.email.0');
        $unknown = $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@studio.test', 'password' => 'errada'])
            ->assertUnprocessable()->json('errors.email.0');

        $this->assertSame('E-mail ou senha incorretos.', $wrong);
        $this->assertSame($wrong, $unknown);
        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        RateLimiter::clear('ana@studio.test|127.0.0.1');

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ana@studio.test', 'password' => 'errada'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@studio.test', 'password' => 'segredo-forte'])
            ->assertStatus(429)
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@studio.test', 'password' => 'segredo-forte'])->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_no_api_token_is_issued(): void
    {
        $response = $this->postJson('/api/v1/auth/login', ['email' => 'ana@studio.test', 'password' => 'segredo-forte']);

        $this->assertStringNotContainsStringIgnoringCase('token', (string) $response->getContent());
    }
}
