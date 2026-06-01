<?php

namespace Database\Seeders;

use App\Services\BaselineAccessService;
use Illuminate\Database\Seeder;

class ExaminersSeeder extends Seeder
{
    public function run(): void
    {
        // Baseline staff accounts are repaired idempotently so live demo access does not break if old hashes or inactive rows exist. This does not wipe runtime activity.
        app(BaselineAccessService::class)->ensure();
    }
}
