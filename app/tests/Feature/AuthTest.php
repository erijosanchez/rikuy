<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_workspace_and_logs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ada Lovelace',
            'organization' => 'Analytical Engines',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertNotNull($user->organization_id);
        $this->assertSame('Analytical Engines', $user->organization->name);
        $this->assertFalse($user->organization->is_demo);
    }

    public function test_user_can_login_and_logout(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $org = Organization::factory()->create();
        User::factory()->for($org)->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
