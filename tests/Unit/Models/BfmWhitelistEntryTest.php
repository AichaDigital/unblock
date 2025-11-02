<?php

/**
 * Tests for BFM Whitelist Entry Model - Reduced Version
 * Only tests business logic that exists in the model
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

describe('Business Logic', function () {
    test('markAsRemoved sets removed flag and timestamp', function () {
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

    test('can create and retrieve BFM whitelist entry', function () {
        $entry = BfmWhitelistEntry::create([
            'host_id' => $this->host->id,
            'ip_address' => '192.168.1.100',
            'added_at' => now(),
            'expires_at' => now()->addHours(2),
            'notes' => 'Test entry',
        ]);

        $retrieved = BfmWhitelistEntry::where('ip_address', '192.168.1.100')->first();

        expect($retrieved)->toBeInstanceOf(BfmWhitelistEntry::class)
            ->and($retrieved->ip_address)->toBe('192.168.1.100')
            ->and($retrieved->removed)->toBe(false)
            ->and($retrieved->notes)->toBe('Test entry');
    });
});

describe('Relationships', function () {
    test('belongs to host', function () {
        $entry = BfmWhitelistEntry::create([
            'host_id' => $this->host->id,
            'ip_address' => '192.168.1.100',
            'added_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        expect($entry->host)->toBeInstanceOf(Host::class)
            ->and($entry->host->id)->toBe($this->host->id);
    });
});
