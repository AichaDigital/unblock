<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'id' => 1,
            'first_name' => 'Admin',
            'last_name' => 'System',
            'email' => config('unblock.admin_email', 'test@mail.com'),
            'password' => app()->environment('local')
                ? Hash::make('password')
                : Hash::make(bin2hex(random_bytes(8))),
            'is_admin' => true,
        ]);
    }
}
