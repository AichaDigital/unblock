<?php

declare(strict_types=1);

use App\Models\{Host, Report, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Prerequisites Validation
// ============================================================================

test('command fails when no admin user exists', function () {
    // Create non-admin user
    User::factory()->create(['is_admin' => false]);

    $this->artisan('develop:test-email-job')
        ->expectsOutput('❌ No admin user found in database')
        ->assertFailed();
});

test('command fails when no host exists', function () {
    User::factory()->admin()->create();

    $this->artisan('develop:test-email-job')
        ->expectsOutput('❌ No host found in database')
        ->assertFailed();
});

// ============================================================================
// SCENARIO 2: Successful Execution
// ============================================================================

test('command creates test report successfully', function () {
    $admin = User::factory()->admin()->create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@test.com',
    ]);

    $host = Host::factory()->create(['fqdn' => 'test.example.com']);

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('🚀 Testing Email Job System...')
        ->assertSuccessful();

    // Verify report was created
    expect(Report::count())->toBe(1);

    $report = Report::first();
    expect($report->user_id)->toBe($admin->id)
        ->and($report->host_id)->toBe($host->id)
        ->and($report->ip)->toBe('203.0.113.1');
});

test('command displays admin and host information', function () {
    $admin = User::factory()->admin()->create([
        'first_name' => 'John',
        'last_name' => 'Admin',
        'email' => 'john@test.com',
    ]);

    $host = Host::factory()->create(['fqdn' => 'server.example.com']);

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('👤 Using Admin: John Admin (john@test.com)')
        ->expectsOutput('🖥️  Using Host: server.example.com')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 3: Email Recipient Flags
// ============================================================================

test('command sends to both admin and user by default', function () {
    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job')
        ->expectsOutput('📧 Sending emails to: Admin + User')
        ->assertSuccessful();
});

test('command sends to admin only with --admin-only flag', function () {
    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('📧 Sending emails to: Admin only')
        ->assertSuccessful();
});

test('command sends to user only with --user-only flag', function () {
    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--user-only' => true])
        ->expectsOutput('📧 Sending emails to: User only')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 4: Queue Configuration Display
// ============================================================================

test('command shows sync queue configuration', function () {
    config()->set('queue.default', 'sync');

    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('📋 Queue Connection: sync')
        ->expectsOutput('🔄 Queue is SYNC - Job executed immediately')
        ->expectsOutput('📧 Check your email inbox for test messages')
        ->assertSuccessful();
});

test('command shows redis queue configuration with instructions', function () {
    config()->set('queue.default', 'redis');

    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('📋 Queue Connection: redis')
        ->expectsOutput('⏳ Queue is REDIS - Job is waiting for worker')
        ->expectsOutput('🔧 Run worker to process job:')
        ->expectsOutput('   php artisan queue:work redis')
        ->assertSuccessful();
});

test('command shows custom queue configuration', function () {
    config()->set('queue.default', 'database');

    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('📋 Queue Connection: database')
        ->expectsOutput('⏳ Queue is database - Job dispatched')
        ->expectsOutput('🔧 Make sure queue worker is running:')
        ->expectsOutput('   php artisan queue:work database')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 5: Report Creation Details
// ============================================================================

test('command creates report with correct test IP', function () {
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->assertSuccessful();

    $report = Report::first();
    expect($report->ip)->toBe('203.0.113.1'); // RFC 5737 test IP
});

test('command creates report with unblocked status false by default', function () {
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->assertSuccessful();

    $report = Report::first();
    expect($report->was_unblocked)->toBeFalse();
});

test('command creates report with relationships', function () {
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->assertSuccessful();

    $report = Report::first();
    // Verify relationships work
    expect($report->user)->toBeInstanceOf(User::class)
        ->and($report->user->id)->toBe($admin->id)
        ->and($report->host)->toBeInstanceOf(Host::class)
        ->and($report->host->id)->toBe($host->id);
});

// ============================================================================
// SCENARIO 6: Observer Integration
// ============================================================================

test('command indicates observer dispatches job automatically', function () {
    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('✅ Job dispatched automatically by Observer!')
        ->assertSuccessful();
});

test('command shows report ID after creation', function () {
    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutputToContain('📄 Test Report ID:')
        ->assertSuccessful();

    $report = Report::first();
    // Report uses UUIDs, not integers
    expect($report->id)->toBeString()
        ->and(strlen($report->id))->toBe(36); // UUID length
});

// ============================================================================
// SCENARIO 7: Next Steps Display
// ============================================================================

test('command displays monitoring instructions', function () {
    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('📋 Next Steps:')
        ->expectsOutput('🔍 Monitor logs:')
        ->expectsOutput('   tail -f storage/logs/laravel.log')
        ->assertSuccessful();
});

test('command shows queue monitor command for redis', function () {
    config()->set('queue.default', 'redis');

    $admin = User::factory()->admin()->create();
    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('📊 Check job status:')
        ->expectsOutput('   php artisan develop:queue-monitor')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 8: Multiple Admins and Hosts
// ============================================================================

test('command uses first admin when multiple exist', function () {
    $firstAdmin = User::factory()->admin()->create([
        'first_name' => 'First',
        'last_name' => 'Admin',
        'email' => 'first@test.com',
        'created_at' => now()->subDays(2),
    ]);

    User::factory()->admin()->create([
        'created_at' => now()->subDays(1),
    ]);

    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('👤 Using Admin: First Admin (first@test.com)')
        ->assertSuccessful();

    $report = Report::first();
    expect($report->user_id)->toBe($firstAdmin->id);
});

test('command uses first host when multiple exist', function () {
    $admin = User::factory()->admin()->create();

    $firstHost = Host::factory()->create([
        'fqdn' => 'first.example.com',
        'created_at' => now()->subDays(2),
    ]);

    Host::factory()->create([
        'created_at' => now()->subDays(1),
    ]);

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('🖥️  Using Host: first.example.com')
        ->assertSuccessful();

    $report = Report::first();
    expect($report->host_id)->toBe($firstHost->id);
});

// ============================================================================
// SCENARIO 9: Edge Cases
// ============================================================================

test('command handles admin with special characters in name', function () {
    $admin = User::factory()->admin()->create([
        'first_name' => 'José',
        'last_name' => 'O\'Brien',
        'email' => 'jose@test.com',
    ]);

    Host::factory()->create();

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('👤 Using Admin: José O\'Brien (jose@test.com)')
        ->assertSuccessful();
});

test('command handles host with international domain', function () {
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create(['fqdn' => 'ñoño.example.com']);

    $this->artisan('develop:test-email-job', ['--admin-only' => true])
        ->expectsOutput('🖥️  Using Host: ñoño.example.com')
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 10: Command Signature and Description
// ============================================================================

test('command has correct signature', function () {
    $command = new \App\Console\Commands\TestEmailJobCommand;

    expect($command->getName())->toBe('develop:test-email-job');
});

test('command has correct description', function () {
    $command = new \App\Console\Commands\TestEmailJobCommand;

    expect($command->getDescription())->toBe('Test email sending via jobs for development purposes');
});
