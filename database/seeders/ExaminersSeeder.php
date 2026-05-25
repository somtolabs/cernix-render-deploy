<?php

namespace Database\Seeders;

use App\Models\Examiner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExaminersSeeder extends Seeder
{
    public function run(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('examiners');

        $rows = [
            [
                'full_name'     => 'Examiner One',
                'username'      => 'examiner1',
                'password_hash' => bcrypt('password123'),
                'role'          => 'examiner',
                'is_active'     => true,
                'created_at'    => now(),
            ],
            [
                'full_name'     => 'Admin One',
                'username'      => 'admin1',
                'password_hash' => bcrypt('admin123'),
                'role'          => 'admin',
                'is_active'     => true,
                'created_at'    => now(),
            ],
            [
                'full_name'     => 'Super Admin',
                'username'      => 'superadmin',
                'password_hash' => bcrypt('superadmin123'),
                'role'          => 'super_admin',
                'is_active'     => true,
                'created_at'    => now(),
            ],
        ];

        $rows = array_map(
            fn (array $row) => array_intersect_key($row + ['admin_user_id' => null, 'last_active_at' => null], array_flip($columns)),
            $rows
        );

        foreach ($rows as $row) {
            Examiner::firstOrCreate(
                ['username' => $row['username']],
                $row
            );
        }
    }
}
