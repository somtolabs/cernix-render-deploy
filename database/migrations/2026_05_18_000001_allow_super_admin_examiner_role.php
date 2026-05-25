<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('examiners')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            return;
        }

        $sql = collect(DB::select("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'examiners'"))->first()->sql ?? '';

        if (str_contains($sql, "'super_admin'")) {
            return;
        }

        $columns = Schema::getColumnListing('examiners');
        $hasAdminUser = in_array('admin_user_id', $columns, true);
        $hasLastActive = in_array('last_active_at', $columns, true);

        DB::statement('PRAGMA foreign_keys=OFF');

        DB::statement('CREATE TABLE examiners_role_fix (
            examiner_id integer primary key autoincrement not null,
            full_name varchar not null,
            username varchar not null,
            password_hash varchar not null,
            role varchar check (role in (\'examiner\', \'admin\', \'super_admin\')) not null,
            ' . ($hasAdminUser ? 'admin_user_id integer null,' : '') . '
            is_active tinyint(1) not null default 0,
            ' . ($hasLastActive ? 'last_active_at datetime null,' : '') . '
            created_at datetime not null
        )');

        DB::statement('INSERT INTO examiners_role_fix (
            examiner_id, full_name, username, password_hash, role, ' . ($hasAdminUser ? 'admin_user_id,' : '') . ' is_active, ' . ($hasLastActive ? 'last_active_at,' : '') . ' created_at
        )
        SELECT
            examiner_id,
            full_name,
            username,
            password_hash,
            CASE
                WHEN UPPER(role) = \'SUPER_ADMIN\' THEN \'super_admin\'
                WHEN UPPER(role) = \'ADMIN\' THEN \'admin\'
                ELSE \'examiner\'
            END,
            ' . ($hasAdminUser ? 'admin_user_id,' : '') . '
            is_active,
            ' . ($hasLastActive ? 'last_active_at,' : '') . '
            created_at
        FROM examiners');

        DB::statement('DROP TABLE examiners');
        DB::statement('ALTER TABLE examiners_role_fix RENAME TO examiners');
        DB::statement('CREATE UNIQUE INDEX examiners_username_unique ON examiners (username)');

        if ($hasAdminUser) {
            DB::statement('CREATE INDEX examiners_admin_user_id_index ON examiners (admin_user_id)');
        }

        if ($hasLastActive) {
            DB::statement('CREATE INDEX examiners_last_active_at_index ON examiners (last_active_at)');
        }

        DB::statement('PRAGMA foreign_keys=ON');
    }

    public function down(): void
    {
        // Intentionally left as a forward-safe data migration. Downgrading the
        // CHECK constraint could strand existing super_admin rows.
    }
};
