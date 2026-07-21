<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One ready-to-use account per role, for local development.
 *
 * Four accounts rather than two: each role has its own registration and login
 * endpoints, and AuthService scopes every lookup by role, so a developer
 * exercising the admin endpoints cannot reuse the customer's credentials.
 *
 * Kept out of DatabaseSeeder so it can be re-run on its own
 * (`db:seed --class=DevUserSeeder`) without re-walking the CMS tables, and so
 * DatabaseSeeder stays a readable manifest.
 *
 * Guarded against production: these are known-password accounts on a .test
 * domain, and the one failure mode worth engineering against here is them
 * reaching a real environment.
 */
class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $accounts = [
            ['email' => 'admin@mealhub.test', 'firstName' => 'admin', 'lastName' => 'user', 'role' => 'admin', 'mobile' => '01700000000'],
            ['email' => 'customer@mealhub.test', 'firstName' => 'test', 'lastName' => 'customer', 'role' => 'customer', 'mobile' => '01800000000'],
            ['email' => 'restaurant@mealhub.test', 'firstName' => 'test', 'lastName' => 'restaurant', 'role' => 'restaurant', 'mobile' => '01900000000'],
            ['email' => 'rider@mealhub.test', 'firstName' => 'test', 'lastName' => 'rider', 'role' => 'rider', 'mobile' => '01600000000'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'firstName' => $account['firstName'],
                    'lastName' => $account['lastName'],
                    'mobile' => $account['mobile'],
                    'role' => $account['role'],
                    'password' => Hash::make('password'),
                    'accept_registration_tnc' => true,
                    // '0' is the cleared sentinel — never a usable code.
                    'otp' => '0',
                    'status' => true,
                    'is_email_verified' => true,
                ]
            );
        }
    }
}
