<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\{CreateSimpleUnblockReportAction, UnblockDecision};
use App\Models\{Host, Report, User};
use App\Services\AnonymousUserService;
use App\Services\Firewall\FirewallAnalysisResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('action creates report with all data correctly', function () {
    $host = Host::factory()->create();
    $ip = '192.168.1.100';
    $domain = 'example.com';
    $email = 'user@example.com';

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: [
            'csf' => 'csfd: 192.168.1.100 (US/United States/-) 5 failed logins in the last 3600 secs - Sun Jan  1 00:00:00 2023',
            'exim' => 'Some exim logs',
            'dovecot' => 'Some dovecot logs',
        ],
        analysis: []
    );

    $unblockResults = [
        'csf_unblock' => 'success',
        'csf_whitelist' => 'success',
    ];

    $decision = UnblockDecision::unblock('IP is blocked and should be unblocked');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        $domain,
        $email,
        $host,
        $analysis,
        $unblockResults,
        $decision
    );

    expect($report)->toBeInstanceOf(Report::class)
        ->and($report->ip)->toBe($ip)
        ->and($report->host_id)->toBe($host->id)
        ->and($report->was_unblocked)->toBeTrue()
        ->and($report->user_id)->toBe(AnonymousUserService::get()->id)
        ->and($report->analysis)->toBeArray()
        ->and($report->analysis['was_blocked'])->toBeTrue()
        ->and($report->analysis['domain'])->toBe($domain)
        ->and($report->analysis['email'])->toBe($email)
        ->and($report->analysis['simple_mode'])->toBeTrue()
        ->and($report->analysis['unblock_performed'])->toBeTrue()
        ->and($report->analysis['decision_reason'])->toBe('IP is blocked and should be unblocked')
        ->and($report->logs)->toBe($analysis->getLogs());
});

test('action creates report with unblock decision to not unblock', function () {
    // Create anonymous user first to avoid foreign key constraint
    $anonymousUser = User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';
    $domain = 'example.com';
    $email = 'user@example.com';

    $analysis = new FirewallAnalysisResult(
        blocked: false,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::noMatch('IP is not blocked');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        $domain,
        $email,
        $host,
        $analysis,
        null,
        $decision
    );

    expect($report->was_unblocked)->toBeFalse()
        ->and($report->analysis['unblock_performed'])->toBeFalse()
        ->and($report->analysis['decision_reason'])->toBe('IP is not blocked')
        ->and($report->analysis['unblock_status'])->toBeNull();
});

test('action parses CSF output and includes block summary', function () {
    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    // Use a realistic CSF output that would trigger isBlocked()
    $csfOutput = <<<'CSF'
csf.deny: 192.168.1.100 # lfd: (csfd) 5 failed logins in the last 3600 secs - Sun Jan  1 00:00:00 2023
CSF;

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => $csfOutput, 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::unblock('Test');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        [],
        $decision
    );

    // Verify block_summary is an array and has expected structure
    expect($report->analysis['block_summary'])->toBeArray()
        ->and($report->analysis['block_summary'])->toHaveKeys(['blocked', 'block_type', 'reason_short', 'attempts', 'location', 'blocked_since']);
});

test('action handles empty CSF output gracefully', function () {
    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    $analysis = new FirewallAnalysisResult(
        blocked: false,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::noMatch('No block found');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        null,
        $decision
    );

    expect($report->analysis['block_summary'])->toBeNull();
});

test('action logs report creation', function () {
    Log::spy();

    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';
    $domain = 'example.com';

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'test', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::unblock('Test reason');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        $domain,
        'user@example.com',
        $host,
        $analysis,
        [],
        $decision
    );

    Log::shouldHaveReceived('info')
        ->with('Creating simple unblock report', Mockery::on(function ($context) use ($ip, $domain, $host, $decision) {
            return $context['ip'] === $ip
                && $context['domain'] === $domain
                && $context['host_id'] === $host->id
                && $context['decision'] === $decision->reason;
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('Simple unblock report created', Mockery::on(function ($context) use ($ip, $domain, $report) {
            return $context['report_id'] === $report->id
                && $context['ip'] === $ip
                && $context['domain'] === $domain;
        }))
        ->once();
});

test('action stores analysis timestamp in ISO format', function () {
    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::unblock('Test');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        [],
        $decision
    );

    expect($report->analysis['analysis_timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

test('action handles null unblock results', function () {
    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    $analysis = new FirewallAnalysisResult(
        blocked: false,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::noMatch('Not blocked');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        null,
        $decision
    );

    expect($report->analysis['unblock_status'])->toBeNull();
});

test('action uses anonymous user service for user_id', function () {
    // Create the anonymous user first
    $anonymousUser = User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::unblock('Test');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        [],
        $decision
    );

    expect($report->user_id)->toBe($anonymousUser->id)
        ->and($anonymousUser->email)->toBe('anonymous@system.local')
        ->and($anonymousUser->first_name)->toBe('Anonymous')
        ->and($anonymousUser->last_name)->toBe('System');
});

test('action stores complete unblock results structure', function () {
    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $unblockResults = [
        'csf_unblock' => 'success',
        'csf_whitelist' => 'success',
        'bfm_remove' => 'not_found',
        'bfm_whitelist' => 'added',
    ];

    $decision = UnblockDecision::unblock('Test');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        $unblockResults,
        $decision
    );

    expect($report->analysis['unblock_status'])->toBe($unblockResults)
        ->and($report->analysis['unblock_status']['csf_unblock'])->toBe('success')
        ->and($report->analysis['unblock_status']['bfm_whitelist'])->toBe('added');
});

test('action marks simple_mode flag as true', function () {
    User::factory()->create([
        'first_name' => 'Anonymous',
        'last_name' => 'System',
        'email' => 'anonymous@system.local',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create();
    $ip = '192.168.1.100';

    $analysis = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => '', 'exim' => '', 'dovecot' => ''],
        analysis: []
    );

    $decision = UnblockDecision::unblock('Test');

    $report = CreateSimpleUnblockReportAction::run(
        $ip,
        'example.com',
        'user@example.com',
        $host,
        $analysis,
        [],
        $decision
    );

    expect($report->analysis['simple_mode'])->toBeTrue();
});
