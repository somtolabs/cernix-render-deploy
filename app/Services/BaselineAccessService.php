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

                $values = [
                    'full_name' => $account['full_name'],
                    'role' => $account['role'],
                    'is_active' => true,
                    'password_hash' => $existing
                        && $this->passwordMatches($account['password'], (string) $existing->password_hash)
                            ? $existing->password_hash
                            : Hash::make($account['password']),
                ];

                if (isset($columns['created_at'])) {
                    $values['created_at'] = $existing?->created_at ?? now();
                }

                if (isset($columns['updated_at'])) {
                    $values['updated_at'] = now();
                }

                DB::table('examiners')->updateOrInsert(
                    ['username' => $account['username']],
                    array_intersect_key($values, $columns)
                );

                $ensured[] = $account['username'];
            }
        });

        return $ensured;
    }

    private function passwordMatches(string $password, string $hash): bool
    {
        try {
            return $hash !== '' && Hash::check($password, $hash);
        } catch (\Throwable) {
            return false;
        }
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
