<?php

use App\Models\{Account, Domain, Host, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests for Account and Domain model relationships
 */
test('account belongs to host', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create(['host_id' => $host->id]);

    expect($account->host)->toBeInstanceOf(Host::class)
        ->and($account->host->id)->toBe($host->id);
});

test('account belongs to user (nullable)', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withUser($user)->create();

    expect($account->user)->toBeInstanceOf(User::class)
        ->and($account->user->id)->toBe($user->id);
});

test('account can have null user', function () {
    $account = Account::factory()->create(['user_id' => null]);

    expect($account->user)->toBeNull();
});

test('account has many domains', function () {
    $account = Account::factory()->create();
    $domains = Domain::factory()->count(3)->create(['account_id' => $account->id]);

    expect($account->domains)->toHaveCount(3)
        ->and($account->domains->first())->toBeInstanceOf(Domain::class)
        ->and($account->domains->pluck('id')->toArray())->toBe($domains->pluck('id')->toArray());
});

test('domain belongs to account', function () {
    $account = Account::factory()->create();
    $domain = Domain::factory()->create(['account_id' => $account->id]);

    expect($domain->account)->toBeInstanceOf(Account::class)
        ->and($domain->account->id)->toBe($account->id);
});

test('account active scope excludes suspended and deleted', function () {
    Account::factory()->create(); // Active
    Account::factory()->suspended()->create();
    Account::factory()->deleted()->create();
    Account::factory()->suspended()->create();

    $activeAccounts = Account::active()->get();

    expect($activeAccounts)->toHaveCount(1);
});

test('account suspended scope only includes suspended', function () {
    Account::factory()->create(); // Active
    Account::factory()->suspended()->create();
    Account::factory()->suspended()->create();
    Account::factory()->deleted()->create();

    $suspendedAccounts = Account::suspended()->get();

    expect($suspendedAccounts)->toHaveCount(2);
});

test('account markedAsDeleted scope only includes deleted', function () {
    Account::factory()->create(); // Active
    Account::factory()->deleted()->create();
    Account::factory()->deleted()->create();
    Account::factory()->suspended()->create();

    $deletedAccounts = Account::markedAsDeleted()->get();

    expect($deletedAccounts)->toHaveCount(2);
});

test('domain forDomain scope finds by normalized name', function () {
    $domain = Domain::factory()->create(['domain_name' => 'example.com']);

    $foundUpper = Domain::forDomain('EXAMPLE.COM')->first();
    $foundSpaced = Domain::forDomain('  example.com  ')->first();
    $foundNormal = Domain::forDomain('example.com')->first();

    expect($foundUpper->id)->toBe($domain->id)
        ->and($foundSpaced->id)->toBe($domain->id)
        ->and($foundNormal->id)->toBe($domain->id);
});

test('domain primary scope only includes primary domains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->addon()->create();
    Domain::factory()->subdomain()->create();
    Domain::factory()->create(['type' => 'primary']);

    $primaryDomains = Domain::primary()->get();

    expect($primaryDomains)->toHaveCount(2);
});

test('domain addon scope only includes addon domains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->addon()->create();
    Domain::factory()->addon()->create();
    Domain::factory()->subdomain()->create();

    $addonDomains = Domain::addon()->get();

    expect($addonDomains)->toHaveCount(2);
});

test('domain subdomain scope only includes subdomains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->subdomain()->create();
    Domain::factory()->subdomain()->create();
    Domain::factory()->addon()->create();

    $subdomains = Domain::subdomain()->get();

    expect($subdomains)->toHaveCount(2);
});

test('domain alias scope only includes alias domains', function () {
    Domain::factory()->create(['type' => 'primary']);
    Domain::factory()->alias()->create();
    Domain::factory()->alias()->create();
    Domain::factory()->addon()->create();

    $aliases = Domain::alias()->get();

    expect($aliases)->toHaveCount(2);
});

test('deleting account cascades to domains', function () {
    $account = Account::factory()->create();
    $domainIds = Domain::factory()->count(3)->create(['account_id' => $account->id])->pluck('id');

    $account->delete();

    expect(Domain::whereIn('id', $domainIds)->count())->toBe(0);
});

test('force deleting host cascades to accounts and domains', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create(['host_id' => $host->id]);
    $domainIds = Domain::factory()->count(2)->create(['account_id' => $account->id])->pluck('id');

    $host->forceDelete(); // Force delete because Host uses SoftDeletes

    expect(Account::where('host_id', $host->id)->count())->toBe(0)
        ->and(Domain::whereIn('id', $domainIds)->count())->toBe(0);
});

test('force deleting user sets account user_id to null', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withUser($user)->create();

    $user->forceDelete(); // Force delete because User uses SoftDeletes

    $account->refresh();

    expect($account->user_id)->toBeNull();
});

test('account factory states work correctly', function () {
    $suspended = Account::factory()->suspended()->create();
    $deleted = Account::factory()->deleted()->create();
    $withUser = Account::factory()->withUser()->create();
    $recentlySynced = Account::factory()->recentlySynced()->create();
    $stale = Account::factory()->staleSync()->create();

    expect($suspended->suspended_at)->not->toBeNull()
        ->and($deleted->deleted_at)->not->toBeNull()
        ->and($withUser->user_id)->not->toBeNull()
        ->and($recentlySynced->last_synced_at)->toBeBetween(now()->subMinutes(31), now())
        ->and($stale->last_synced_at)->toBeBetween(now()->subDays(8), now()->subHours(23));
});

test('domain factory states work correctly', function () {
    $primary = Domain::factory()->create();
    $addon = Domain::factory()->addon()->create();
    $subdomain = Domain::factory()->subdomain()->create();
    $alias = Domain::factory()->alias()->create();
    $custom = Domain::factory()->withDomainName('CUSTOM.COM')->create();

    expect($primary->type)->toBe('primary')
        ->and($addon->type)->toBe('addon')
        ->and($subdomain->type)->toBe('subdomain')
        ->and($subdomain->domain_name)->toContain('sub.')
        ->and($alias->type)->toBe('alias')
        ->and($custom->domain_name)->toBe('custom.com');
});
