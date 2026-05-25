<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Student register
    // -------------------------------------------------------------------------

    public function test_student_can_register(): void
    {
        $response = $this->postJson('/api/student/register', [
            'name'                  => 'John Student',
            'email'                 => 'student@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['user', 'token', 'token_type', 'expires_in'],
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'role'  => 'student',
        ]);
    }

    public function test_student_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'student@example.com', 'role' => 'student']);

        $response = $this->postJson('/api/student/register', [
            'name'                  => 'Another',
            'email'                 => 'student@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Student login
    // -------------------------------------------------------------------------

    public function test_student_can_login(): void
    {
        User::factory()->create([
            'email'    => 'student@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'student',
        ]);

        $response = $this->postJson('/api/student/login', [
            'email'    => 'student@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['user', 'token', 'token_type', 'expires_in'],
                 ]);
    }

    public function test_student_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'student@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'student',
        ]);

        $response = $this->postJson('/api/student/login', [
            'email'    => 'student@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('status', 'error');
    }

    public function test_student_login_rejects_examiner_credentials(): void
    {
        User::factory()->create([
            'email'    => 'examiner@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'examiner',
        ]);

        $response = $this->postJson('/api/student/login', [
            'email'    => 'examiner@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Examiner login
    // -------------------------------------------------------------------------

    public function test_examiner_can_login(): void
    {
        User::factory()->create([
            'email'    => 'examiner@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'examiner',
        ]);

        $response = $this->postJson('/api/examiner/login', [
            'email'    => 'examiner@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['user', 'token', 'token_type', 'expires_in'],
                 ]);
    }

    public function test_examiner_login_rejects_student_credentials(): void
    {
        User::factory()->create([
            'email'    => 'student@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'student',
        ]);

        $response = $this->postJson('/api/examiner/login', [
            'email'    => 'student@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Admin login
    // -------------------------------------------------------------------------

    public function test_admin_can_login(): void
    {
        User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['user', 'token', 'token_type', 'expires_in'],
                 ]);
    }

    // -------------------------------------------------------------------------
    // Protected endpoints
    // -------------------------------------------------------------------------

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $token = auth('api')->login($user);

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/auth/logout')
             ->assertStatus(200)
             ->assertJsonPath('status', 'success');
    }

    public function test_token_can_be_refreshed(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $token = auth('api')->login($user);

        $response = $this->withToken($token)->postJson('/api/auth/refresh');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['token', 'token_type', 'expires_in'],
                 ]);
    }
}
