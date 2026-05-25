<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRoleControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_logout_redirects_to_admin_login(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();

        $this->withSession([
            'examiner_id' => $admin->examiner_id,
            'examiner_username' => $admin->username,
            'examiner_name' => $admin->full_name,
            'examiner_role' => $admin->role,
        ])->get('/admin/logout')->assertRedirect('/admin/login');
    }

    public function test_examiner_logout_redirects_to_examiner_login(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        $this->withSession([
            'examiner_id' => $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name' => $examiner->full_name,
            'examiner_role' => $examiner->role,
        ])->get('/examiner/logout')->assertRedirect('/examiner/login');
    }

    public function test_super_admin_cannot_login_to_examiner_portal(): void
    {
        $this->postJson('/examiner/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is not permitted to access the Examiner portal.');
    }

    public function test_admin_cannot_login_to_examiner_portal(): void
    {
        $this->postJson('/examiner/login', [
            'username' => 'admin1',
            'password' => 'admin123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is not permitted to access the Examiner portal.');
    }

    public function test_examiner_cannot_login_to_admin_portal(): void
    {
        $this->postJson('/admin/login', [
            'username' => 'examiner1',
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is not permitted to access the Admin portal.');
    }

    public function test_examiner_only_session_can_open_scanner_dashboard(): void
    {
        $super = DB::table('examiners')->where('username', 'superadmin')->first();
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        $this->withSession([
            'examiner_id' => $super->examiner_id,
            'examiner_username' => $super->username,
            'examiner_name' => $super->full_name,
            'examiner_role' => $super->role,
        ])->get('/examiner/dashboard')->assertRedirect('/examiner/login');

        $this->withSession([
            'examiner_id' => $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name' => $examiner->full_name,
            'examiner_role' => $examiner->role,
        ])->get('/examiner/dashboard')->assertOk();
    }

    public function test_admin_like_session_reaches_examiner_login_denial_not_admin_dashboard(): void
    {
        $super = DB::table('examiners')->where('username', 'superadmin')->first();

        $this->followingRedirects()
            ->withSession([
                'examiner_id' => $super->examiner_id,
                'examiner_username' => $super->username,
                'examiner_name' => $super->full_name,
                'examiner_role' => $super->role,
            ])
            ->get('/examiner/dashboard')
            ->assertOk()
            ->assertSee('Examiner Login')
            ->assertSee('This account is not permitted to access the Examiner portal.')
            ->assertDontSee('Super Admin Control Center');
    }

    public function test_examiner_session_reaches_admin_login_denial_not_admin_dashboard(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        $this->followingRedirects()
            ->withSession([
                'examiner_id' => $examiner->examiner_id,
                'examiner_username' => $examiner->username,
                'examiner_name' => $examiner->full_name,
                'examiner_role' => $examiner->role,
            ])
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Login')
            ->assertSee('Admin access required. Sign in with an admin account.')
            ->assertDontSee('Admin Operations');
    }

    public function test_admin_session_cannot_post_to_examiner_verification(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();

        $this->withSession([
            'examiner_id' => $admin->examiner_id,
            'examiner_username' => $admin->username,
            'examiner_name' => $admin->full_name,
            'examiner_role' => $admin->role,
        ])->postJson('/examiner/verify', [
            'qr_data' => ['token_id' => 'not-a-real-token'],
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Not authenticated.');
    }

    public function test_admin_cannot_create_super_admin(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();

        $this->withSession([
            'examiner_id' => $admin->examiner_id,
            'examiner_username' => $admin->username,
            'examiner_name' => $admin->full_name,
            'examiner_role' => $admin->role,
        ])->post(route('admin.examiners.store'), [
            'full_name' => 'Blocked Super Admin',
            'username' => 'blocked_super',
            'password' => 'password123',
            'role' => 'super_admin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('examiners', ['username' => 'blocked_super']);
    }

    public function test_super_admin_can_see_settings_controls(): void
    {
        $super = DB::table('examiners')->where('username', 'superadmin')->first();

        $this->withSession([
            'examiner_id' => $super->examiner_id,
            'examiner_username' => $super->username,
            'examiner_name' => $super->full_name,
            'examiner_role' => $super->role,
        ])->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Save Fee Mapping')
            ->assertSee('Save Demo Mode');
    }
}
