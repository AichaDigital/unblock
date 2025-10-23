<?php

/**
 * Tests for BFM Whitelist Entry Model
 */

use App\Models\{BfmWhitelistEntry, Host};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->host = Host::factory()->create([
        'panel' => 'directadmin',
        'fqdn' => 'test.example.com',
    ]);
});

test('can create BFM whitelist entry', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
        'notes' => 'Test entry',
    ]);

    expect($entry)->toBeInstanceOf(BfmWhitelistEntry::class)
        ->and($entry->ip_address)->toBe('192.168.1.100')
        ->and($entry->removed)->toBe(false);
});

test('isExpired returns true for expired entries', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now()->subHours(3),
        'expires_at' => now()->subHour(),
        'notes' => 'Expired entry',
    ]);

    expect($entry->isExpired())->toBeTrue();
});

test('isExpired returns false for non-expired entries', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
        'notes' => 'Active entry',
    ]);

    expect($entry->isExpired())->toBeFalse();
});

test('isActive returns true for active entries', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    expect($entry->isActive())->toBeTrue();
});

test('isActive returns false for removed entries', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
        'removed' => true,
        'removed_at' => now(),
    ]);

    expect($entry->isActive())->toBeFalse();
});

test('isActive returns false for expired entries', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now()->subHours(3),
        'expires_at' => now()->subHour(),
    ]);

    expect($entry->isActive())->toBeFalse();
});

test('markAsRemoved updates entry correctly', function () {
    $entry = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $entry->markAsRemoved();

    expect($entry->removed)->toBeTrue()
        ->and($entry->removed_at)->not->toBeNull();
});

test('active scope returns only active entries', function () {
    // Active entry
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    // Expired entry
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.101',
        'added_at' => now()->subHours(3),
        'expires_at' => now()->subHour(),
    ]);

    // Removed entry
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.102',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
        'removed' => true,
        'removed_at' => now(),
    ]);

    $activeEntries = BfmWhitelistEntry::active()->get();

    expect($activeEntries)->toHaveCount(1)
        ->and($activeEntries->first()->ip_address)->toBe('192.168.1.100');
});

test('expired scope returns only expired non-removed entries', function () {
    // Active entry
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    // Expired entry 1
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.101',
        'added_at' => now()->subHours(3),
        'expires_at' => now()->subHour(),
    ]);

    // Expired entry 2
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.102',
        'added_at' => now()->subHours(4),
        'expires_at' => now()->subHours(2),
    ]);

    // Expired and removed entry
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.103',
        'added_at' => now()->subHours(3),
        'expires_at' => now()->subHour(),
        'removed' => true,
        'removed_at' => now(),
    ]);

    $expiredEntries = BfmWhitelistEntry::expired()->get();

    expect($expiredEntries)->toHaveCount(2)
        ->and($expiredEntries->pluck('ip_address')->toArray())
        ->toContain('192.168.1.101', '192.168.1.102');
});

test('forHost scope filters by host', function () {
    $host2 = Host::factory()->create([
        'panel' => 'directadmin',
        'fqdn' => 'test2.example.com',
    ]);

    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    BfmWhitelistEntry::create([
        'host_id' => $host2->id,
        'ip_address' => '192.168.1.101',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $entriesForHost1 = BfmWhitelistEntry::forHost($this->host->id)->get();

    expect($entriesForHost1)->toHaveCount(1)
        ->and($entriesForHost1->first()->ip_address)->toBe('192.168.1.100');
});

test('forIp scope filters by IP address', function () {
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.101',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $entriesForIp = BfmWhitelistEntry::forIp('192.168.1.100')->get();

    expect($entriesForIp)->toHaveCount(1)
        ->and($entriesForIp->first()->ip_address)->toBe('192.168.1.100');
});

test('unique constraint prevents duplicate active entries for same host and IP', function () {
    BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    // Attempt to create duplicate - should throw exception
    expect(fn () => BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('allows creating new entry after marking previous as removed', function () {
    $entry1 = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $entry1->markAsRemoved();

    // Should be able to create new entry with same host and IP after removal
    $entry2 = BfmWhitelistEntry::create([
        'host_id' => $this->host->id,
        'ip_address' => '192.168.1.100',
        'added_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    expect($entry2)->toBeInstanceOf(BfmWhitelistEntry::class)
        ->and($entry2->id)->not->toBe($entry1->id);
});
