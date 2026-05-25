<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_new_tables_exist(): void
    {
        $tables = [
            'departments',
            'exam_sessions',
            'mock_sis',
            'students',
            'payment_records',
            'qr_tokens',
            'examiners',
            'verification_logs',
            'audit_log',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Table [{$table}] does not exist."
            );
        }
    }
}
