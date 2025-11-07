<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\UnblockDecision;

test('UnblockDecision can be constructed with shouldUnblock true', function () {
    $decision = new UnblockDecision(
        shouldUnblock: true,
        reason: 'IP is blocked and should be unblocked'
    );

    expect($decision->shouldUnblock)->toBeTrue()
        ->and($decision->reason)->toBe('IP is blocked and should be unblocked');
});

test('UnblockDecision can be constructed with shouldUnblock false', function () {
    $decision = new UnblockDecision(
        shouldUnblock: false,
        reason: 'IP is not blocked'
    );

    expect($decision->shouldUnblock)->toBeFalse()
        ->and($decision->reason)->toBe('IP is not blocked');
});

test('UnblockDecision has unblock factory method', function () {
    $decision = UnblockDecision::unblock('IP found in block list');

    expect($decision->shouldUnblock)->toBeTrue()
        ->and($decision->reason)->toBe('IP found in block list');
});

test('UnblockDecision has gatherData factory method', function () {
    $decision = UnblockDecision::gatherData('Logs found but no block');

    expect($decision->shouldUnblock)->toBeFalse()
        ->and($decision->reason)->toBe('Logs found but no block');
});

test('UnblockDecision has noMatch factory method', function () {
    $decision = UnblockDecision::noMatch('IP not found in any logs');

    expect($decision->shouldUnblock)->toBeFalse()
        ->and($decision->reason)->toBe('IP not found in any logs');
});

test('UnblockDecision has abort factory method', function () {
    $decision = UnblockDecision::abort('Invalid domain or account suspended');

    expect($decision->shouldUnblock)->toBeFalse()
        ->and($decision->reason)->toBe('Invalid domain or account suspended');
});

test('UnblockDecision is immutable', function () {
    $decision = UnblockDecision::unblock('Test reason');

    $reflection = new ReflectionClass($decision);

    expect($reflection->isReadOnly())->toBeTrue();
});

test('UnblockDecision factory methods return correct shouldUnblock values', function () {
    expect(UnblockDecision::unblock('test')->shouldUnblock)->toBeTrue()
        ->and(UnblockDecision::gatherData('test')->shouldUnblock)->toBeFalse()
        ->and(UnblockDecision::noMatch('test')->shouldUnblock)->toBeFalse()
        ->and(UnblockDecision::abort('test')->shouldUnblock)->toBeFalse();
});

test('UnblockDecision preserves reason exactly as provided', function () {
    $reason = 'Very specific reason with special chars: !@#$%';
    $decision = UnblockDecision::unblock($reason);

    expect($decision->reason)->toBe($reason);
});

test('UnblockDecision accepts empty reason', function () {
    $decision = UnblockDecision::unblock('');

    expect($decision->reason)->toBe('');
});

test('UnblockDecision multiple instances are independent', function () {
    $decision1 = UnblockDecision::unblock('Reason 1');
    $decision2 = UnblockDecision::noMatch('Reason 2');

    expect($decision1->shouldUnblock)->toBeTrue()
        ->and($decision1->reason)->toBe('Reason 1')
        ->and($decision2->shouldUnblock)->toBeFalse()
        ->and($decision2->reason)->toBe('Reason 2');
});
