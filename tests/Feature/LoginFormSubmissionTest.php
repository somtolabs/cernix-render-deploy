<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_login_page_renders_a_normal_post_form(): void
    {
        $content = $this->get('/admin/login')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('method="POST"', $content);
        $this->assertStringContainsString('action="' . url('/admin/login') . '"', $content);
        $this->assertStringContainsString('name="_token"', $content);
        $this->assertStringContainsString('name="username"', $content);
        $this->assertStringContainsString('name="password"', $content);
        $this->assertStringContainsString('type="submit"', $content);
        $this->assertStringNotContainsString('preventDefault', $content);
        $this->assertStringNotContainsString('fetch(', $content);
    }

    public function test_examiner_login_page_renders_a_normal_post_form(): void
    {
        $content = $this->get('/examiner/login')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('method="POST"', $content);
        $this->assertStringContainsString('action="' . url('/examiner/login') . '"', $content);
        $this->assertStringContainsString('name="_token"', $content);
        $this->assertStringContainsString('name="username"', $content);
        $this->assertStringContainsString('name="password"', $content);
        $this->assertStringContainsString('type="submit"', $content);
        $this->assertStringNotContainsString('preventDefault', $content);
        $this->assertStringNotContainsString('fetch(', $content);
    }

    public function test_admin_login_form_post_redirects_to_dashboard(): void
    {
        $this->post('/admin/login', [
            'username' => 'admin1',
            'password' => 'admin123',
        ])->assertRedirect('/admin/dashboard');
    }

    public function test_super_admin_baseline_login_succeeds_and_wrong_password_fails(): void
    {
        $this->post('/admin/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ])->assertRedirect('/admin/dashboard');

        $this->post('/admin/logout');

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'superadmin',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHas('error', 'Invalid credentials.');
    }

    public function test_examiner_login_form_post_redirects_to_dashboard(): void
    {
        $this->post('/examiner/login', [
            'username' => 'examiner1',
            'password' => 'password123',
        ])->assertRedirect('/examiner/dashboard');
    }

    public function test_failed_html_login_returns_visible_error_message(): void
    {
        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'admin1',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHas('error', 'Invalid credentials.');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Invalid credentials.');
    }

    public function test_json_login_contract_still_returns_json(): void
    {
        $this->postJson('/admin/login', [
            'username' => 'admin1',
            'password' => 'admin123',
        ])->assertOk()
            ->assertJsonPath('redirect_url', '/admin/dashboard');
    }
}
