<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\AnonymousUserService;
use Illuminate\Database\Seeder;

/**
 * Anonymous User Seeder
 *
 * Creates the system anonymous user for simple unblock mode.
 * This user represents anonymous requests that don't require authentication.
 */
class AnonymousUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = AnonymousUserService::get();

        $this->command->info("Anonymous system user created/verified: {$user->email}");
    }
}
