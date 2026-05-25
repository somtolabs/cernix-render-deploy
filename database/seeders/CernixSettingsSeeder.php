<?php

namespace Database\Seeders;

use App\Support\DepartmentFees;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CernixSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('cernix_settings')) {
            return;
        }

        $defaults = [
            'school_fee_mapping' => json_encode(DepartmentFees::FEES),
            'demo_mode_enabled' => 'false',
        ];

        foreach ($defaults as $key => $value) {
            if (DB::table('cernix_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('cernix_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
