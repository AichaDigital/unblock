<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\{EvaluateUnblockMatchAction, UnblockDecision};
use Illuminate\Support\Facades\Log;

test('action returns unblock decision when IP is blocked', function () {
    $decision = EvaluateUnblockMatchAction::run(
        ipIsBlocked: true,
        domainFoundInLogs: false,
        domainValidInDb: true
    );

    expect($decision)->toBeInstanceOf(UnblockDecision::class)
        ->and($decision->shouldUnblock)->toBeTrue()
        ->and($decision->reason)->toBe('ip_blocked_in_csf');
});

test('action returns unblock decision when IP is blocked regardless of logs', function () {
    $decision = EvaluateUnblockMatchAction::run(
        ipIsBlocked: true,
        domainFoundInLogs: true,
        domainValidInDb: true
    );

    expect($decision)->toBeInstanceOf(UnblockDecision::class)
        ->and($decision->shouldUnblock)->toBeTrue()
        ->and($decision->reason)->toBe('ip_blocked_in_csf');
});

test('action returns gatherData decision when IP not blocked but logs found', function () {
    $decision = EvaluateUnblockMatchAction::run(
        ipIsBlocked: false,
        domainFoundInLogs: true,
        domainValidInDb: true
    );

    expect($decision)->toBeInstanceOf(UnblockDecision::class)
        ->and($decision->shouldUnblock)->toBeFalse()
        ->and($decision->reason)->toBe('logs_found_no_block');
});

test('action returns noMatch decision when IP not blocked and no logs', function () {
    $decision = EvaluateUnblockMatchAction::run(
        ipIsBlocked: false,
        domainFoundInLogs: false,
        domainValidInDb: true
    );

    expect($decision)->toBeInstanceOf(UnblockDecision::class)
        ->and($decision->shouldUnblock)->toBeFalse()
        ->and($decision->reason)->toBe('ip_not_blocked_no_logs');
});

test('action logs evaluation process', function () {
    Log::spy();

    EvaluateUnblockMatchAction::run(
        ipIsBlocked: true,
        domainFoundInLogs: false,
        domainValidInDb: true
    );

    Log::shouldHaveReceived('info')
        ->with('Evaluating unblock decision', Mockery::on(function ($context) {
            return isset($context['ip_blocked'])
                && isset($context['domain_in_logs']);
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('Unblock evaluation: IP is blocked in CSF. Decision: UNBLOCK')
        ->once();
});

test('action logs when logs found but not blocked', function () {
    Log::spy();

    EvaluateUnblockMatchAction::run(
        ipIsBlocked: false,
        domainFoundInLogs: true,
        domainValidInDb: true
    );

    Log::shouldHaveReceived('info')
        ->with('Unblock evaluation: IP not blocked, but logs found. Decision: GATHER_DATA')
        ->once();
});

test('action logs when no match found', function () {
    Log::spy();

    EvaluateUnblockMatchAction::run(
        ipIsBlocked: false,
        domainFoundInLogs: false,
        domainValidInDb: true
    );

    Log::shouldHaveReceived('info')
        ->with('Unblock evaluation: IP not blocked and no logs found. Decision: NO_MATCH')
        ->once();
});

test('action domainValidInDb parameter is deprecated but accepted', function () {
    // Test that deprecated parameter doesn't affect logic
    $decision1 = EvaluateUnblockMatchAction::run(
        ipIsBlocked: true,
        domainFoundInLogs: false,
        domainValidInDb: true
    );

    $decision2 = EvaluateUnblockMatchAction::run(
        ipIsBlocked: true,
        domainFoundInLogs: false,
        domainValidInDb: false  // Deprecated parameter value doesn't matter
    );

    expect($decision1->shouldUnblock)->toBe($decision2->shouldUnblock)
        ->and($decision1->reason)->toBe($decision2->reason);
});

