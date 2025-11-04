<?php

declare(strict_types=1);

use App\Models\{BfmWhitelistEntry, Host};

test('bfm whitelist entry belongs to host', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create(['host_id' => $host->id]);

    expect($entry->host)->toBeInstanceOf(Host::class)
        ->and($entry->host->id)->toBe($host->id);
});

test('bfm whitelist entry is expired when expires_at is in past', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now()->subHours(25),
        'expires_at' => now()->subHour(),
    ]);

    expect($entry->isExpired())->toBeTrue();
});

test('bfm whitelist entry is not expired when expires_at is in future', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
    ]);

    expect($entry->isExpired())->toBeFalse();
});

test('bfm whitelist entry is active when not removed and not expired', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
        'removed' => false,
    ]);

    expect($entry->isActive())->toBeTrue();
});

test('bfm whitelist entry is not active when removed', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
        'removed' => true,
    ]);

    expect($entry->isActive())->toBeFalse();
});

test('bfm whitelist entry is not active when expired', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now()->subHours(25),
        'expires_at' => now()->subHour(),
        'removed' => false,
    ]);

    expect($entry->isActive())->toBeFalse();
});

test('bfm whitelist entry mark as removed updates status and timestamp', function () {
    $host = Host::factory()->create();
    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
        'removed' => false,
        'removed_at' => null,
    ]);

    $entry->markAsRemoved();

    expect($entry->removed)->toBeTrue()
        ->and($entry->removed_at)->not->toBeNull()
        ->and($entry->removed_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('bfm whitelist entry active scope returns only active entries', function () {
    $host = Host::factory()->create();

    // Active entry
    $active = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
        'removed' => false,
    ]);

    // Expired entry
    BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now()->subHours(25),
        'expires_at' => now()->subHour(),
        'removed' => false,
    ]);

    // Removed entry
    BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
        'removed' => true,
    ]);

    $activeEntries = BfmWhitelistEntry::active()->get();

    expect($activeEntries)->toHaveCount(1)
        ->and($activeEntries->first()->id)->toBe($active->id);
});

test('bfm whitelist entry expired scope returns only expired entries', function () {
    $host = Host::factory()->create();

    // Active entry
    BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
        'removed' => false,
    ]);

    // Expired entry
    $expired = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now()->subHours(25),
        'expires_at' => now()->subHour(),
        'removed' => false,
    ]);

    // Removed expired entry (should not appear)
    BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => now()->subHours(25),
        'expires_at' => now()->subHour(),
        'removed' => true,
    ]);

    $expiredEntries = BfmWhitelistEntry::expired()->get();

    expect($expiredEntries)->toHaveCount(1)
        ->and($expiredEntries->first()->id)->toBe($expired->id);
});

test('bfm whitelist entry for host scope filters by host id', function () {
    $host1 = Host::factory()->create();
    $host2 = Host::factory()->create();

    $entry1 = BfmWhitelistEntry::factory()->create(['host_id' => $host1->id]);
    BfmWhitelistEntry::factory()->create(['host_id' => $host2->id]);

    $host1Entries = BfmWhitelistEntry::forHost($host1->id)->get();

    expect($host1Entries)->toHaveCount(1)
        ->and($host1Entries->first()->id)->toBe($entry1->id);
});

test('bfm whitelist entry for ip scope filters by ip address', function () {
    $host = Host::factory()->create();

    $entry1 = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'ip_address' => '192.168.1.1',
    ]);

    BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'ip_address' => '10.0.0.1',
    ]);

    $ipEntries = BfmWhitelistEntry::forIp('192.168.1.1')->get();

    expect($ipEntries)->toHaveCount(1)
        ->and($ipEntries->first()->id)->toBe($entry1->id);
});

test('bfm whitelist entry defaults removed to false', function () {
    $host = Host::factory()->create();
    $entry = new BfmWhitelistEntry([
        'host_id' => $host->id,
        'ip_address' => '192.168.1.1',
        'added_at' => now(),
        'expires_at' => now()->addHours(24),
    ]);

    expect($entry->removed)->toBeFalse();
});

test('bfm whitelist entry casts dates correctly', function () {
    $host = Host::factory()->create();
    $now = now();

    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'added_at' => $now,
        'expires_at' => $now->addHours(24),
    ]);

    expect($entry->added_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($entry->expires_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($entry->created_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($entry->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('bfm whitelist entry can store notes', function () {
    $host = Host::factory()->create();
    $notes = 'Test notes for manual tracking';

    $entry = BfmWhitelistEntry::factory()->create([
        'host_id' => $host->id,
        'notes' => $notes,
    ]);

    expect($entry->notes)->toBe($notes);
});
