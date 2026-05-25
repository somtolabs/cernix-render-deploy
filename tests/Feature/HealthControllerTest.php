<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_exposes_active_session_context_and_counts(): void
    {
        $this->seed();

        $response = $this->getJson('/health');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'connected')
            ->assertJsonPath('session_active', true)
            ->assertJsonPath('active_session_id', 1)
            ->assertJsonPath('active_session_label', 'First Semester 2025/2026');

        $payload = $response->json();

        $this->assertGreaterThanOrEqual(1, $payload['active_examiner_count']);
        $this->assertGreaterThanOrEqual(1500, $payload['mock_student_count']);
    }
}
