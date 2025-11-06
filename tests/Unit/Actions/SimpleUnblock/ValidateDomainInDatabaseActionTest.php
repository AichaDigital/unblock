<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\ValidateDomainInDatabaseAction;
use App\Models\{Account, Domain, Host};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('action validates domain successfully when domain exists and account is active', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'suspended_at' => null,
        'deleted_at' => null,
    ]);
    $domain = Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'example.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('example.com', $host->id);

    expect($result->exists)->toBeTrue()
        ->and($result->domain)->not->toBeNull()
        ->and($result->domain->id)->toBe($domain->id)
        ->and($result->reason)->toBeNull();
});

test('action fails validation when domain does not exist', function () {
    $host = Host::factory()->create();

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('nonexistent.com', $host->id);

    expect($result->exists)->toBeFalse()
        ->and($result->domain)->toBeNull()
        ->and($result->reason)->toBe('domain_not_found');

    Log::shouldHaveReceived('info')->with('Domain validation failed: not found in database', Mockery::any());
});

test('action fails validation when domain exists but belongs to different host', function () {
    $host1 = Host::factory()->create();
    $host2 = Host::factory()->create();

    $account = Account::factory()->create([
        'host_id' => $host1->id,
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'example.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('example.com', $host2->id);

    expect($result->exists)->toBeFalse()
        ->and($result->reason)->toBe('domain_not_found');
});

test('action fails validation when account is suspended', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'suspended_at' => now()->subDay(),
        'deleted_at' => null,
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'suspended.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('suspended.com', $host->id);

    expect($result->exists)->toBeFalse()
        ->and($result->domain)->toBeNull()
        ->and($result->reason)->toBe('account_suspended');

    Log::shouldHaveReceived('info')->with('Domain validation failed: account suspended', Mockery::any());
});

test('action fails validation when account is deleted', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'suspended_at' => null,
        'deleted_at' => now()->subDay(),
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'deleted.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('deleted.com', $host->id);

    expect($result->exists)->toBeFalse()
        ->and($result->domain)->toBeNull()
        ->and($result->reason)->toBe('account_deleted');

    Log::shouldHaveReceived('info')->with('Domain validation failed: account deleted', Mockery::any());
});

test('action logs info when validation starts', function () {
    $host = Host::factory()->create();

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $action->handle('example.com', $host->id);

    Log::shouldHaveReceived('info')->with('Validating domain in database', Mockery::on(function ($context) {
        return $context['domain'] === 'example.com'
            && isset($context['host_id']);
    }));
});

test('action logs info when validation passes', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
    ]);
    $domain = Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'example.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $action->handle('example.com', $host->id);

    Log::shouldHaveReceived('info')->with('Domain validation passed', Mockery::on(function ($context) use ($domain, $account, $host) {
        return $context['domain'] === 'example.com'
            && $context['domain_id'] === $domain->id
            && $context['account_id'] === $account->id
            && $context['host_id'] === $host->id;
    }));
});

test('action handles case-insensitive domain search', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'example.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $resultUpper = $action->handle('EXAMPLE.COM', $host->id);
    $resultMixed = $action->handle('ExAmPlE.CoM', $host->id);

    expect($resultUpper->exists)->toBeTrue()
        ->and($resultMixed->exists)->toBeTrue();
});

test('action handles subdomains correctly', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'subdomain.example.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('subdomain.example.com', $host->id);

    expect($result->exists)->toBeTrue();
});

test('action handles multiple domains on same host', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
    ]);

    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'domain1.com',
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'domain2.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result1 = $action->handle('domain1.com', $host->id);
    $result2 = $action->handle('domain2.com', $host->id);

    expect($result1->exists)->toBeTrue()
        ->and($result2->exists)->toBeTrue()
        ->and($result1->domain->domain_name)->toBe('domain1.com')
        ->and($result2->domain->domain_name)->toBe('domain2.com');
});

test('action handles addon domains correctly', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'domain' => 'primary.com',
    ]);

    // Primary domain
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'primary.com',
    ]);

    // Addon domain
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'addon.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $primaryResult = $action->handle('primary.com', $host->id);
    $addonResult = $action->handle('addon.com', $host->id);

    expect($primaryResult->exists)->toBeTrue()
        ->and($addonResult->exists)->toBeTrue();
});

test('action eager loads account relationship to avoid N+1 queries', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'example.com',
    ]);

    Log::spy();

    // Enable query logging
    \DB::enableQueryLog();

    $action = new ValidateDomainInDatabaseAction;
    $action->handle('example.com', $host->id);

    $queries = \DB::getQueryLog();

    // Should be 1 or 2 queries (depending on how eager loading is implemented)
    // The important part is that it includes accounts relationship
    expect($queries)->not->toBeEmpty()
        ->and($queries[0]['query'])->toContain('accounts');

    \DB::disableQueryLog();
});

test('action fails when account is both suspended and deleted', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'suspended_at' => now()->subWeek(),
        'deleted_at' => now()->subDay(),
    ]);
    Domain::factory()->create([
        'account_id' => $account->id,
        'domain_name' => 'both.com',
    ]);

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('both.com', $host->id);

    // Should fail on suspended check first (before deleted check)
    expect($result->exists)->toBeFalse()
        ->and($result->reason)->toBe('account_suspended');
});

test('action returns DomainValidationResult object', function () {
    $host = Host::factory()->create();

    Log::spy();

    $action = new ValidateDomainInDatabaseAction;
    $result = $action->handle('example.com', $host->id);

    expect($result)->toBeInstanceOf(\App\Actions\SimpleUnblock\DomainValidationResult::class);
});
