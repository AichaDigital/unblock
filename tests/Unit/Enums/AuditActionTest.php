<?php

declare(strict_types=1);

use App\Enums\AuditAction;

test('AuditAction has Email case with value 0', function () {
    expect(AuditAction::Email->value)->toBe(0)
        ->and(AuditAction::Email)->toBeInstanceOf(AuditAction::class);
});

test('AuditAction has Access case with value 1', function () {
    expect(AuditAction::Access->value)->toBe(1)
        ->and(AuditAction::Access)->toBeInstanceOf(AuditAction::class);
});

test('AuditAction has Clean case with value 2', function () {
    expect(AuditAction::Clean->value)->toBe(2)
        ->and(AuditAction::Clean)->toBeInstanceOf(AuditAction::class);
});

test('AuditAction has Success case with value 3', function () {
    expect(AuditAction::Success->value)->toBe(3)
        ->and(AuditAction::Success)->toBeInstanceOf(AuditAction::class);
});

test('AuditAction has TooManyRequests case with value 4', function () {
    expect(AuditAction::TooManyRequests->value)->toBe(4)
        ->and(AuditAction::TooManyRequests)->toBeInstanceOf(AuditAction::class);
});

test('AuditAction has OtpLogin case with value 5', function () {
    expect(AuditAction::OtpLogin->value)->toBe(5)
        ->and(AuditAction::OtpLogin)->toBeInstanceOf(AuditAction::class);
});

test('AuditAction getValueByKey returns correct value for email', function () {
    expect(AuditAction::getValueByKey('email'))->toBe(0);
});

test('AuditAction getValueByKey returns correct value for access', function () {
    expect(AuditAction::getValueByKey('access'))->toBe(1);
});

test('AuditAction getValueByKey returns correct value for clean', function () {
    expect(AuditAction::getValueByKey('clean'))->toBe(2);
});

test('AuditAction getValueByKey returns correct value for success', function () {
    expect(AuditAction::getValueByKey('success'))->toBe(3);
});

test('AuditAction getValueByKey returns correct value for too_many_requests', function () {
    expect(AuditAction::getValueByKey('too_many_requests'))->toBe(4);
});

test('AuditAction getValueByKey returns correct value for otp_login', function () {
    expect(AuditAction::getValueByKey('otp_login'))->toBe(5);
});

test('AuditAction getValueByKey throws exception for invalid key', function () {
    AuditAction::getValueByKey('invalid_key');
})->throws(UnhandledMatchError::class);

test('AuditAction can be instantiated from integer value', function () {
    expect(AuditAction::from(0))->toBe(AuditAction::Email)
        ->and(AuditAction::from(1))->toBe(AuditAction::Access)
        ->and(AuditAction::from(2))->toBe(AuditAction::Clean)
        ->and(AuditAction::from(3))->toBe(AuditAction::Success)
        ->and(AuditAction::from(4))->toBe(AuditAction::TooManyRequests)
        ->and(AuditAction::from(5))->toBe(AuditAction::OtpLogin);
});

test('AuditAction tryFrom returns null for invalid value', function () {
    expect(AuditAction::tryFrom(999))->toBeNull();
});

test('AuditAction from throws exception for invalid value', function () {
    AuditAction::from(999);
})->throws(ValueError::class);

test('AuditAction cases returns all enum cases', function () {
    $cases = AuditAction::cases();

    expect($cases)->toBeArray()
        ->toHaveCount(6)
        ->and($cases[0])->toBe(AuditAction::Email)
        ->and($cases[1])->toBe(AuditAction::Access)
        ->and($cases[2])->toBe(AuditAction::Clean)
        ->and($cases[3])->toBe(AuditAction::Success)
        ->and($cases[4])->toBe(AuditAction::TooManyRequests)
        ->and($cases[5])->toBe(AuditAction::OtpLogin);
});
