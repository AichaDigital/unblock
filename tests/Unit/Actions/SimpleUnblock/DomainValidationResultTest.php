<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\DomainValidationResult;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('DomainValidationResult can be constructed with success', function () {
    $domain = Domain::factory()->create();

    $result = new DomainValidationResult(
        exists: true,
        domain: $domain,
        reason: null
    );

    expect($result->exists)->toBeTrue()
        ->and($result->domain)->toBe($domain)
        ->and($result->reason)->toBeNull();
});

test('DomainValidationResult can be constructed with failure', function () {
    $result = new DomainValidationResult(
        exists: false,
        domain: null,
        reason: 'Domain not found'
    );

    expect($result->exists)->toBeFalse()
        ->and($result->domain)->toBeNull()
        ->and($result->reason)->toBe('Domain not found');
});

test('DomainValidationResult has success factory method', function () {
    $domain = Domain::factory()->create();

    $result = DomainValidationResult::success($domain);

    expect($result->exists)->toBeTrue()
        ->and($result->domain)->toBe($domain)
        ->and($result->reason)->toBeNull();
});

test('DomainValidationResult has failure factory method', function () {
    $result = DomainValidationResult::failure('Domain does not exist');

    expect($result->exists)->toBeFalse()
        ->and($result->domain)->toBeNull()
        ->and($result->reason)->toBe('Domain does not exist');
});

test('DomainValidationResult is immutable', function () {
    $result = DomainValidationResult::failure('Test');

    $reflection = new ReflectionClass($result);

    expect($reflection->isReadOnly())->toBeTrue();
});
