<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'antigo@email.com', 'password' => 'senha1234']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', ['name' => 'Ana', 'email' => 'novo@email.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->patchJson('/api/v1/auth/profile', ['name' => 'Ana', 'email' => 'novo@email.com', 'current_password' => 'errada99'])
            ->assertUnprocessable();

        $this->assertSame('antigo@email.com', $user->fresh()->email);

        // Changing only the name needs no password.
        $this->patchJson('/api/v1/auth/profile', ['name' => 'Ana Lima', 'email' => 'antigo@email.com'])->assertOk();
    }

    public function test_user_updates_own_name_and_email(): void
    {
        $user = User::factory()->create(['password' => 'senha1234']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', ['name' => 'Novo Nome', 'email' => ' Novo@Email.com ', 'current_password' => 'senha1234'])
            ->assertOk()
            ->assertJsonPath('data.email', 'novo@email.com');

        $this->assertSame('Novo Nome', $user->fresh()->name);
    }

    public function test_email_must_be_unique_among_users(): void
    {
        User::factory()->create(['email' => 'ocupado@email.com']);

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/v1/auth/profile', ['name' => 'Ana', 'email' => 'ocupado@email.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'antiga123']);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'errada123',
                'password' => 'nova12345',
                'password_confirmation' => 'nova12345',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'A senha atual está incorreta.');

        $this->assertTrue(Hash::check('antiga123', $user->fresh()->password));
    }

    public function test_password_change_validates_strength_and_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['password' => 'antiga123']))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'antiga123',
                'password' => 'curta',
                'password_confirmation' => 'diferente',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_password_is_changed(): void
    {
        $user = User::factory()->create(['password' => 'antiga123']);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'antiga123',
                'password' => 'nova12345',
                'password_confirmation' => 'nova12345',
            ])
            ->assertNoContent();

        $this->assertTrue(Hash::check('nova12345', $user->fresh()->password));
    }

    public function test_guests_cannot_change_profile(): void
    {
        $this->patchJson('/api/v1/auth/profile', ['name' => 'X', 'email' => 'x@x.com'])->assertUnauthorized();
        $this->putJson('/api/v1/auth/password', [])->assertUnauthorized();
    }
}
