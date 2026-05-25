<?php

namespace Tests\Feature;

use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audit = new AuditService();
    }

    // -------------------------------------------------------------------------
    // logAction — correct storage
    // -------------------------------------------------------------------------

    public function test_action_is_stored_correctly(): void
    {
        $this->audit->logAction('CSC/2021/001', 'student', 'token.issued');

        $this->assertDatabaseHas('audit_log', [
            'actor_id'   => 'CSC/2021/001',
            'actor_type' => 'student',
            'action'     => 'token.issued',
        ]);
    }

    public function test_metadata_is_saved_as_json(): void
    {
        $this->audit->logAction('CSC/2021/001', 'student', 'payment.verified', [
            'rrr_number' => '280007021192',
            'amount'     => 10000.00,
        ]);

        $row = DB::table('audit_log')
            ->where('action', 'payment.verified')
            ->first();

        $this->assertNotNull($row);

        $decoded = json_decode($row->metadata, true);
        $this->assertSame('280007021192', $decoded['rrr_number']);
        $this->assertSame(10000.0, (float) $decoded['amount']);
    }

    public function test_empty_metadata_is_stored_as_empty_json_object(): void
    {
        $this->audit->logAction('system', 'system', 'session.created');

        $row = DB::table('audit_log')
            ->where('action', 'session.created')
            ->first();

        $this->assertSame('[]', $row->metadata);
    }

    public function test_timestamp_is_stored(): void
    {
        $this->audit->logAction('1', 'examiner', 'token.revoked');

        $row = DB::table('audit_log')
            ->where('action', 'token.revoked')
            ->first();

        $this->assertNotNull($row->timestamp);
        $this->assertNotEmpty($row->timestamp);
    }

    public function test_multiple_actions_each_create_separate_rows(): void
    {
        $this->audit->logAction('CSC/2021/001', 'student', 'token.issued');
        $this->audit->logAction('CSC/2021/001', 'student', 'token.issued');
        $this->audit->logAction('CSC/2021/001', 'student', 'token.issued');

        $count = DB::table('audit_log')
            ->where('actor_id', 'CSC/2021/001')
            ->where('action', 'token.issued')
            ->count();

        $this->assertSame(3, $count);
    }

    public function test_different_actor_types_are_stored_correctly(): void
    {
        $this->audit->logAction('CSC/2021/001', 'student',  'login');
        $this->audit->logAction('1',            'examiner', 'scan.performed');
        $this->audit->logAction('system',       'system',   'session.activated');

        $this->assertDatabaseHas('audit_log', ['actor_type' => 'student',  'action' => 'login']);
        $this->assertDatabaseHas('audit_log', ['actor_type' => 'examiner', 'action' => 'scan.performed']);
        $this->assertDatabaseHas('audit_log', ['actor_type' => 'system',   'action' => 'session.activated']);
    }

    // -------------------------------------------------------------------------
    // Append-only — no update or delete behaviour
    // -------------------------------------------------------------------------

    public function test_log_count_only_grows(): void
    {
        $this->audit->logAction('actor', 'system', 'event.one');
        $countAfterFirst = DB::table('audit_log')->count();

        $this->audit->logAction('actor', 'system', 'event.two');
        $countAfterSecond = DB::table('audit_log')->count();

        $this->assertGreaterThan($countAfterFirst, $countAfterSecond,
            'audit_log row count must only grow — records are append-only');
    }

    public function test_existing_entries_are_unchanged_after_new_log(): void
    {
        $this->audit->logAction('CSC/2021/001', 'student', 'token.issued', ['session_id' => 1]);

        $before = DB::table('audit_log')->first();

        // Add more entries
        $this->audit->logAction('CSC/2021/001', 'student', 'payment.verified');
        $this->audit->logAction('system',       'system',  'session.created');

        $after = DB::table('audit_log')->where('id', $before->id)->first();

        $this->assertSame($before->actor_id,   $after->actor_id);
        $this->assertSame($before->action,     $after->action);
        $this->assertSame($before->metadata,   $after->metadata);
        $this->assertSame($before->timestamp,  $after->timestamp);
    }

    // -------------------------------------------------------------------------
    // encodeMetadata helper
    // -------------------------------------------------------------------------

    public function test_encode_metadata_returns_valid_json(): void
    {
        $json = $this->audit->encodeMetadata(['key' => 'value', 'count' => 42]);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame('value', $decoded['key']);
        $this->assertSame(42, $decoded['count']);
    }

    public function test_encode_metadata_handles_nested_arrays(): void
    {
        $json = $this->audit->encodeMetadata([
            'student' => ['matric_no' => 'CSC/2021/001', 'session_id' => 1],
        ]);

        $decoded = json_decode($json, true);
        $this->assertSame('CSC/2021/001', $decoded['student']['matric_no']);
    }

    public function test_encode_metadata_returns_empty_array_json_for_empty_input(): void
    {
        $this->assertSame('[]', $this->audit->encodeMetadata([]));
    }
}
