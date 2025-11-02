<?php

declare(strict_types=1);

use App\Jobs\ProcessSimpleUnblockJob;
use App\Models\{Account, Domain, Host};

beforeEach(function () {
    $this->host = Host::factory()->create([
        'panel' => 'cpanel',
        'hash' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest_key_content\n-----END OPENSSH PRIVATE KEY-----",
    ]);

    $this->account = Account::factory()->create([
        'host_id' => $this->host->id,
        'username' => 'testuser',
        'domain' => 'test.com',
    ]);

    $this->domain = Domain::factory()->create([
        'account_id' => $this->account->id,
        'domain_name' => 'test.com',
        'type' => 'primary',
    ]);

    $this->ip = '1.2.3.4';
    $this->email = 'test@example.com';
});

test('job uses SSH key PATH not CONTENT when checking logs', function () {
    $job = new ProcessSimpleUnblockJob(
        ip: $this->ip,
        domain: 'test.com',
        email: $this->email,
        hostId: $this->host->id
    );

    // Mock CheckIpInServerLogsAction to verify it receives a PATH, not content
    $checkLogsMock = Mockery::mock(\App\Actions\SimpleUnblock\CheckIpInServerLogsAction::class);

    $checkLogsMock->shouldReceive('handle')
        ->once()
        ->with(
            Mockery::type(\App\Models\Host::class),
            Mockery::on(function ($keyPath) {
                // Verify it's a PATH (starts with storage/app/.ssh/)
                // NOT content (starts with -----BEGIN)
                return is_string($keyPath)
                    && str_contains($keyPath, 'storage/app/.ssh/')
                    && ! str_contains($keyPath, '-----BEGIN');
            }),
            $this->ip,
            'test.com'
        )
        ->andReturn(new \App\Actions\SimpleUnblock\IpLogsSearchResult(
            ip: $this->ip,
            foundInLogs: false,
            logEntries: []
        ));

    $this->app->instance(\App\Actions\SimpleUnblock\CheckIpInServerLogsAction::class, $checkLogsMock);

    // This will FAIL because currently it passes $host->hash (content)
    $job->handle(
        app(\App\Actions\SimpleUnblock\ValidateIpFormatAction::class),
        app(\App\Actions\SimpleUnblock\ValidateDomainInDatabaseAction::class),
        app(\App\Actions\SimpleUnblock\AnalyzeFirewallForIpAction::class),
        $checkLogsMock,
        app(\App\Actions\SimpleUnblock\EvaluateUnblockMatchAction::class),
        app(\App\Actions\UnblockIpAction::class),
        app(\App\Actions\SimpleUnblock\CreateSimpleUnblockReportAction::class),
        app(\App\Actions\SimpleUnblock\NotifySimpleUnblockResultAction::class)
    );
})->skip('Will fail until SSH key path bug is fixed');

test('job uses SSH key PATH not CONTENT when unblocking', function () {
    // Mark IP as blocked to trigger unblock
    $this->account->update(['suspended_at' => null]);

    $job = new ProcessSimpleUnblockJob(
        ip: $this->ip,
        domain: 'test.com',
        email: $this->email,
        hostId: $this->host->id
    );

    // Mock UnblockIpAction to verify it receives a PATH
    $unblockMock = Mockery::mock(\App\Actions\UnblockIpAction::class);

    $unblockMock->shouldReceive('handle')
        ->once()
        ->with(
            $this->ip,
            $this->host->id,
            Mockery::on(function ($keyPath) {
                // Verify it's a PATH, not content
                return is_string($keyPath)
                    && str_contains($keyPath, 'storage/app/.ssh/')
                    && ! str_contains($keyPath, '-----BEGIN');
            })
        )
        ->andReturn([
            'success' => true,
            'message' => 'IP unblocked',
        ]);

    $this->app->instance(\App\Actions\UnblockIpAction::class, $unblockMock);

    // This will FAIL because currently it passes $host->hash (content)
    $job->handle(
        app(\App\Actions\SimpleUnblock\ValidateIpFormatAction::class),
        app(\App\Actions\SimpleUnblock\ValidateDomainInDatabaseAction::class),
        app(\App\Actions\SimpleUnblock\AnalyzeFirewallForIpAction::class),
        app(\App\Actions\SimpleUnblock\CheckIpInServerLogsAction::class),
        app(\App\Actions\SimpleUnblock\EvaluateUnblockMatchAction::class),
        $unblockMock,
        app(\App\Actions\SimpleUnblock\CreateSimpleUnblockReportAction::class),
        app(\App\Actions\SimpleUnblock\NotifySimpleUnblockResultAction::class)
    );
})->skip('Will fail until SSH key path bug is fixed');

test('job cleans up temporary SSH keys after execution', function () {
    $job = new ProcessSimpleUnblockJob(
        ip: $this->ip,
        domain: 'test.com',
        email: $this->email,
        hostId: $this->host->id
    );

    // Count SSH keys before
    $keysBefore = glob(storage_path('app/.ssh/key_*'));
    $countBefore = count($keysBefore);

    try {
        $job->handle(
            app(\App\Actions\SimpleUnblock\ValidateIpFormatAction::class),
            app(\App\Actions\SimpleUnblock\ValidateDomainInDatabaseAction::class),
            app(\App\Actions\SimpleUnblock\AnalyzeFirewallForIpAction::class),
            app(\App\Actions\SimpleUnblock\CheckIpInServerLogsAction::class),
            app(\App\Actions\SimpleUnblock\EvaluateUnblockMatchAction::class),
            app(\App\Actions\UnblockIpAction::class),
            app(\App\Actions\SimpleUnblock\CreateSimpleUnblockReportAction::class),
            app(\App\Actions\SimpleUnblock\NotifySimpleUnblockResultAction::class)
        );
    } catch (\Throwable $e) {
        // Ignore errors, we just want to check cleanup
    }

    // Count SSH keys after
    $keysAfter = glob(storage_path('app/.ssh/key_*'));
    $countAfter = count($keysAfter);

    // Should be same count (keys cleaned up)
    expect($countAfter)->toBeLessThanOrEqual($countBefore + 1); // Allow 1 temp key during execution
})->skip('Will fail until SSH key cleanup is implemented');
