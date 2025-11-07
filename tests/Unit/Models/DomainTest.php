<?php

declare(strict_types=1);

use App\Models\{Account, Domain};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Model Structure
// ============================================================================

test('model has correct fillable attributes', function () {
    $domain = new Domain;
    $fillable = $domain->getFillable();

    expect($fillable)->toContain('account_id')
        ->and($fillable)->toContain('domain_name')
        ->and($fillable)->toContain('type');
});

// ============================================================================
// SCENARIO 2: Relationships
// ============================================================================

test('account relationship is defined', function () {
    $account = Account::factory()->create();
    $domain = Domain::factory()->create(['account_id' => $account->id]);

    expect($domain->account)->not->toBeNull()
        ->and($domain->account->id)->toBe($account->id);
});

test('account relationship returns null when account is deleted', function () {
    $account = Account::factory()->create();
    $domain = Domain::factory()->create(['account_id' => $account->id]);

    // Delete account (cascade should delete domain)
    $account->delete();

    expect(Domain::find($domain->id))->toBeNull();
});

// ============================================================================
// SCENARIO 3: Scopes - forDomain
// ============================================================================

test('forDomain scope finds exact match', function () {
    Domain::factory()->create(['domain_name' => 'example.com']);
    Domain::factory()->create(['domain_name' => 'test.com']);

    $result = Domain::forDomain('example.com')->first();

    expect($result)->not->toBeNull()
        ->and($result->domain_name)->toBe('example.com');
});

test('forDomain scope normalizes to lowercase', function () {
    Domain::factory()->create(['domain_name' => 'example.com']);

    $result = Domain::forDomain('EXAMPLE.COM')->first();

    expect($result)->not->toBeNull()
        ->and($result->domain_name)->toBe('example.com');
});

test('forDomain scope trims whitespace', function () {
    Domain::factory()->create(['domain_name' => 'example.com']);

    $result = Domain::forDomain('  example.com  ')->first();

    expect($result)->not->toBeNull()
        ->and($result->domain_name)->toBe('example.com');
});

test('forDomain scope combines trim and lowercase', function () {
    Domain::factory()->create(['domain_name' => 'example.com']);

    $result = Domain::forDomain('  EXAMPLE.COM  ')->first();

    expect($result)->not->toBeNull()
        ->and($result->domain_name)->toBe('example.com');
});

test('forDomain scope returns null for non-existent domain', function () {
    Domain::factory()->create(['domain_name' => 'example.com']);

    $result = Domain::forDomain('nonexistent.com')->first();

    expect($result)->toBeNull();
});

// ============================================================================
// SCENARIO 4: Type Scopes
// ============================================================================

test('primary scope filters primary domains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->create(['type' => 'addon']);
    Domain::factory()->create(['type' => 'subdomain']);

    $primary = Domain::primary()->get();

    expect($primary)->toHaveCount(2)
        ->and($primary->every(fn ($d) => $d->type === 'primary'))->toBeTrue();
});

test('addon scope filters addon domains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->create(['type' => 'addon']);
    Domain::factory()->create(['type' => 'addon']);
    Domain::factory()->create(['type' => 'subdomain']);

    $addon = Domain::addon()->get();

    expect($addon)->toHaveCount(2)
        ->and($addon->every(fn ($d) => $d->type === 'addon'))->toBeTrue();
});

test('subdomain scope filters subdomains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->create(['type' => 'addon']);
    Domain::factory()->create(['type' => 'subdomain']);
    Domain::factory()->create(['type' => 'subdomain']);

    $subdomain = Domain::subdomain()->get();

    expect($subdomain)->toHaveCount(2)
        ->and($subdomain->every(fn ($d) => $d->type === 'subdomain'))->toBeTrue();
});

test('alias scope filters alias domains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->create(['type' => 'addon']);
    Domain::factory()->create(['type' => 'alias']);
    Domain::factory()->create(['type' => 'alias']);

    $alias = Domain::alias()->get();

    expect($alias)->toHaveCount(2)
        ->and($alias->every(fn ($d) => $d->type === 'alias'))->toBeTrue();
});

// ============================================================================
// SCENARIO 5: Combined Scopes
// ============================================================================

test('scopes can be combined', function () {
    $account1 = Account::factory()->create();
    $account2 = Account::factory()->create();

    // Create domains with different types for different accounts
    Domain::factory()->create([
        'account_id' => $account1->id,
        'domain_name' => 'primary1.com',
        'type' => 'primary',
    ]);
    Domain::factory()->create([
        'account_id' => $account1->id,
        'domain_name' => 'addon1.com',
        'type' => 'addon',
    ]);
    Domain::factory()->create([
        'account_id' => $account2->id,
        'domain_name' => 'primary2.com',
        'type' => 'primary',
    ]);

    // Find primary domain for specific account using forDomain + primary scopes
    $result = Domain::forDomain('primary1.com')->primary()->first();

    expect($result)->not->toBeNull()
        ->and($result->domain_name)->toBe('primary1.com')
        ->and($result->type)->toBe('primary')
        ->and($result->account_id)->toBe($account1->id);
});

// ============================================================================
// SCENARIO 6: Edge Cases
// ============================================================================

test('can create multiple domains for same account', function () {
    $account = Account::factory()->create();

    Domain::factory()->create(['account_id' => $account->id, 'type' => 'primary']);
    Domain::factory()->create(['account_id' => $account->id, 'type' => 'addon']);
    Domain::factory()->create(['account_id' => $account->id, 'type' => 'subdomain']);

    expect($account->domains)->toHaveCount(3);
});

test('domain names are case sensitive in database but normalized in scope', function () {
    Domain::factory()->create(['domain_name' => 'example.com']);

    // Direct query is case-sensitive
    $directQuery = Domain::where('domain_name', 'EXAMPLE.COM')->first();
    expect($directQuery)->toBeNull();

    // Scope normalizes
    $scopeQuery = Domain::forDomain('EXAMPLE.COM')->first();
    expect($scopeQuery)->not->toBeNull();
});

test('can create domains with special characters', function () {
    $domain = Domain::factory()->create(['domain_name' => 'test-domain.co.uk']);

    expect($domain->domain_name)->toBe('test-domain.co.uk');
});

test('empty string domain name is allowed by model', function () {
    $domain = Domain::factory()->create(['domain_name' => '']);

    expect($domain->domain_name)->toBe('');
});
