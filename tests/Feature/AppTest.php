<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppTest extends TestCase
{
    public function test_home_returns_200(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
