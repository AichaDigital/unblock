<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: No Records to Clean
// ============================================================================

test('command shows success message when no records to cleanup', function () {
    $this->artisan('simple-unblock:cleanup-otp')
        ->expectsOutput('✓ No OTP records to cleanup.')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 2: Expired Records
// ============================================================================

test('command deletes expired OTP records', function () {
    // Create expired records
    DB::table('one_time_passwords')->insert([
        [
            'password' => 'abc123', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 1,
            'expires_at' => now()->subHours(2),
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ],
        [
            'password' => 'def456', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 2,
            'expires_at' => now()->subMinutes(30),
            'created_at' => now()->subHours(1),
            'updated_at' => now()->subHours(1),
        ],
    ]);

    // Create non-expired record
    DB::table('one_time_passwords')->insert([
        'password' => 'xyz789', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 3,
        'expires_at' => now()->addMinutes(15),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutput('Deleting expired OTP records...')
        ->expectsOutput('  → Deleted 2 expired records')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(1)
        ->and(DB::table('one_time_passwords')->where('password', 'xyz789')->exists())->toBeTrue(); // ggignore
});

// ============================================================================
// SCENARIO 3: Old Records (>7 days)
// ============================================================================

test('command deletes old OTP records by default 7 days', function () {
    // Create old record (10 days ago, not expired)
    DB::table('one_time_passwords')->insert([
        'password' => 'old123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->addDays(100), // Not expired
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    // Create recent record (5 days ago, not expired)
    DB::table('one_time_passwords')->insert([
        'password' => 'recent456', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 2,
        'expires_at' => now()->addDays(1),
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutput('Deleting OTP records older than 7 days...')
        ->expectsOutput('  → Deleted 1 old records')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(1)
        ->and(DB::table('one_time_passwords')->where('password', 'recent456')->exists())->toBeTrue(); // ggignore
});

// ============================================================================
// SCENARIO 4: Custom Days Option
// ============================================================================

test('command accepts custom days option', function () {
    // Create records with different ages
    DB::table('one_time_passwords')->insert([
        [
            'password' => 'token15', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 1,
            'expires_at' => now()->addDays(1),
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(15),
        ],
        [
            'password' => 'token5', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 2,
            'expires_at' => now()->addDays(1),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ],
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--days' => 10, '--force' => true])
        ->expectsOutput('Deleting OTP records older than 10 days...')
        ->expectsOutput('  → Deleted 1 old records')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(1)
        ->and(DB::table('one_time_passwords')->where('password', 'token5')->exists())->toBeTrue(); // ggignore
});

// ============================================================================
// SCENARIO 5: Mixed Expired + Old Records
// ============================================================================

test('command deletes both expired and old records', function () {
    DB::table('one_time_passwords')->insert([
        // Expired
        [
            'password' => 'exp1', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 1,
            'expires_at' => now()->subHours(1),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ],
        // Old but not expired
        [
            'password' => 'old1', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 2,
            'expires_at' => now()->addDays(100),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ],
        // Fresh
        [
            'password' => 'fresh1', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 3,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutput('  → Deleted 1 expired records')
        ->expectsOutput('  → Deleted 1 old records')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(1)
        ->and(DB::table('one_time_passwords')->where('password', 'fresh1')->exists())->toBeTrue(); // ggignore
});

// ============================================================================
// SCENARIO 6: Confirmation
// ============================================================================

test('command prompts for confirmation without force flag', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'test123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->subHours(1),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('simple-unblock:cleanup-otp')
        ->expectsQuestion('Do you want to proceed with cleanup?', true)
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

test('command cancels when confirmation is declined', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'test123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->subHours(1),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('simple-unblock:cleanup-otp')
        ->expectsQuestion('Do you want to proceed with cleanup?', false)
        ->expectsOutput('Cleanup cancelled.')
        ->assertFailed();

    // Record should still exist
    expect(DB::table('one_time_passwords')->count())->toBe(1);
});

test('command skips confirmation with force flag', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'test123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->subHours(1),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->doesntExpectOutputToContain('Do you want to proceed')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

// ============================================================================
// SCENARIO 7: Summary Display
// ============================================================================

test('command displays summary before deletion', function () {
    // Create 3 expired, 2 old
    DB::table('one_time_passwords')->insert([
        ['password' => 't1', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 1, 'expires_at' => now()->subHours(1), 'created_at' => now()->subHours(2), 'updated_at' => now()], // ggignore
        ['password' => 't2', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 2, 'expires_at' => now()->subHours(2), 'created_at' => now()->subHours(3), 'updated_at' => now()], // ggignore
        ['password' => 't3', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 3, 'expires_at' => now()->subHours(3), 'created_at' => now()->subHours(4), 'updated_at' => now()], // ggignore
        ['password' => 't4', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 4, 'expires_at' => now()->addDays(100), 'created_at' => now()->subDays(10), 'updated_at' => now()], // ggignore
        ['password' => 't5', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 5, 'expires_at' => now()->addDays(100), 'created_at' => now()->subDays(8), 'updated_at' => now()], // ggignore
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutput('║     Simple Unblock OTP Cleanup Summary       ║')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 8: Edge Cases
// ============================================================================

test('command handles records with exact expiration time', function () {
    $now = now();

    DB::table('one_time_passwords')->insert([
        'password' => 'exact123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => $now, // Expires exactly now
        'created_at' => $now->copy()->subMinutes(10),
        'updated_at' => $now,
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->assertSuccessful();

    // Should NOT be deleted (not < now, but = now)
    expect(DB::table('one_time_passwords')->count())->toBe(1);
});

test('command handles very old records', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'ancient123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->addYears(1),
        'created_at' => now()->subYears(5),
        'updated_at' => now()->subYears(5),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

test('command handles days option as string', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'test123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->addDays(1),
        'created_at' => now()->subDays(15),
        'updated_at' => now(),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--days' => '10', '--force' => true])
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

test('command handles zero days option', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'today123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'expires_at' => now()->addMinutes(15),
        'created_at' => now()->subMinutes(30),
        'updated_at' => now(),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--days' => 0, '--force' => true])
        ->assertSuccessful();

    // Should delete all non-expired records older than 0 days
    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

// ============================================================================
// SCENARIO 9: Large Datasets
// ============================================================================

test('command handles large number of records efficiently', function () {
    // Create 100 expired records
    $records = [];
    for ($i = 0; $i < 100; $i++) {
        $records[] = [
            'password' => "token{$i}", // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => $i + 1,
            'expires_at' => now()->subHours(rand(1, 10)),
            'created_at' => now()->subHours(rand(11, 20)),
            'updated_at' => now(),
        ];
    }

    DB::table('one_time_passwords')->insert($records);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutput('  → Deleted 100 expired records')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

// ============================================================================
// SCENARIO 10: Success Message
// ============================================================================

test('command shows completion message with total count', function () {
    DB::table('one_time_passwords')->insert([
        ['password' => 't1', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 1, 'expires_at' => now()->subHours(1), 'created_at' => now()->subHours(2), 'updated_at' => now()], // ggignore
        ['password' => 't2', 'authenticatable_type' => 'App\\Models\\User', 'authenticatable_id' => 2, 'expires_at' => now()->addDays(100), 'created_at' => now()->subDays(10), 'updated_at' => now()], // ggignore
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutputToContain('✓ Cleanup completed! Total records deleted: 2')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 11: Polymorphic Relationships
// ============================================================================

test('command handles different authenticatable types', function () {
    DB::table('one_time_passwords')->insert([
        [
            'password' => 'user123', // ggignore
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => 1,
            'expires_at' => now()->subHours(1),
            'created_at' => now()->subHours(2),
            'updated_at' => now(),
        ],
        [
            'password' => 'admin123', // ggignore
            'authenticatable_type' => 'App\\Models\\Admin',
            'authenticatable_id' => 1,
            'expires_at' => now()->subHours(1),
            'created_at' => now()->subHours(2),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->expectsOutput('  → Deleted 2 expired records')
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});

test('command handles origin_properties JSON field', function () {
    DB::table('one_time_passwords')->insert([
        'password' => 'test123', // ggignore
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'origin_properties' => json_encode(['ip' => '192.168.1.1', 'user_agent' => 'Test Browser']),
        'expires_at' => now()->subHours(1),
        'created_at' => now()->subHours(2),
        'updated_at' => now(),
    ]);

    $this->artisan('simple-unblock:cleanup-otp', ['--force' => true])
        ->assertSuccessful();

    expect(DB::table('one_time_passwords')->count())->toBe(0);
});
