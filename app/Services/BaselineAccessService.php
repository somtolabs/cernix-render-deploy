<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BaselineAccessService
{
    /**
     * Repair the baseline staff accounts without modifying runtime activity.
     *
     * @return list<string>
     */
    public function ensure(): array
    {
        $columns = array_flip(DB::getSchemaBuilder()->getColumnListing('examiners'));
        $ensured = [];

        DB::transaction(function () use ($columns, &$ensured): void {
            foreach ($this->accounts() as $account) {
                $existing = DB::table('examiners')
                    ->where('username', $account['username'])
                    ->first();

                $attributes = [
                    'full_name' => $account['full_name'],
                    'role' => $account['role'],
                    'is_active' => true,
                ];

                if (! $existing || ! Hash::check($account['password'], (string) $existing->password_hash)) {
                    $attributes['password_hash'] = Hash::make($account['password']);
                }

                if (isset($columns['updated_at'])) {
                    $attributes['updated_at'] = now();
                }

                $attributes = array_intersect_key($attributes, $columns);

                if ($existing) {
                    DB::table('examiners')
                        ->where('username', $account['username'])
                        ->update($attributes);
                } else {
                    $insert = $attributes + ['username' => $account['username']];

                    if (isset($columns['created_at'])) {
                        $insert['created_at'] = now();
                    }

                    DB::table('examiners')->insert(array_intersect_key($insert, $columns));
                }

                $ensured[] = $account['username'];
            }
        });

        return $ensured;
    }

    /**
     * @return list<array{full_name: string, username: string, password: string, role: string}>
     */
    private function accounts(): array
    {
        return [
            [
                'full_name' => 'Examiner One',
                'username' => 'examiner1',
                'password' => 'password123',
                'role' => 'examiner',
            ],
            [
                'full_name' => 'Admin One',
                'username' => 'admin1',
                'password' => 'admin123',
                'role' => 'admin',
            ],
            [
                'full_name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => 'superadmin123',
                'role' => 'super_admin',
            ],
        ];
    }
}