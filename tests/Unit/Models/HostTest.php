<?php

declare(strict_types=1);

use App\Enums\PanelType;
use App\Models\Host;

test('host encrypts hash attribute on set', function () {
    $plainHash = '-----BEGIN OPENSSH PRIVATE KEY-----
test_private_key_content
-----END OPENSSH PRIVATE KEY-----';

    $host = new Host;
    $host->hash = $plainHash;

    // The attribute should be encrypted in storage
    expect($host->getAttributes()['hash'])->not->toBe($plainHash);

    // But accessible as decrypted
    expect($host->hash)->toBe($plainHash);
});

test('host encrypts hash_public attribute on set', function () {
    $publicKey = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAAB test@example.com';

    $host = new Host;
    $host->hash_public = $publicKey;

    // The attribute should be encrypted in storage
    expect($host->getAttributes()['hash_public'])->not->toBe($publicKey);

    // But accessible as decrypted
    expect($host->hash_public)->toBe($publicKey);
});

test('host returns empty string for null hash', function () {
    $host = new Host;

    expect($host->hash)->toBe('');
});

test('host returns empty string for empty hash', function () {
    $host = new Host;
    $host->setAttribute('hash', '');

    expect($host->hash)->toBe('');
});

test('host handles legacy plaintext hash with openssh marker', function () {
    $legacyHash = '-----BEGIN OPENSSH PRIVATE KEY-----
legacy_unencrypted_key
-----END OPENSSH PRIVATE KEY-----';

    $host = new Host;
    // Simulate legacy data by setting raw attribute
    $host->setAttribute('hash', $legacyHash);

    // Should return the plaintext value
    expect($host->hash)->toBe(trim($legacyHash));
});

test('host handles decryption failure by returning original value', function () {
    $host = new Host;
    // Set an invalid encrypted value that will fail decryption
    $host->setAttribute('hash', 'invalid_encrypted_data_without_openssh_marker');

    // Should return the trimmed original value since it's not decryptable and doesn't have OPENSSH marker
    expect($host->hash)->toBe('invalid_encrypted_data_without_openssh_marker');
});

test('host does not encrypt null hash', function () {
    $host = new Host;
    $host->hash = null;

    expect($host->getAttributes()['hash'] ?? null)->toBeNull();
});

test('host does not encrypt empty string hash', function () {
    $host = new Host;
    $host->hash = '';

    // Should not set the attribute at all
    expect(isset($host->getAttributes()['hash']))->toBeFalse();
});

test('host casts panel to PanelType enum', function () {
    $host = Host::factory()->create(['panel' => PanelType::CPANEL]);

    expect($host->panel)->toBeInstanceOf(PanelType::class)
        ->and($host->panel)->toBe(PanelType::CPANEL);
});

test('host casts port_ssh to integer', function () {
    $host = Host::factory()->create(['port_ssh' => '22']);

    expect($host->port_ssh)->toBeInt()
        ->and($host->port_ssh)->toBe(22);
});

test('host casts hosting_manual to boolean', function () {
    $host1 = Host::factory()->create(['hosting_manual' => 1]);
    $host2 = Host::factory()->create(['hosting_manual' => 0]);

    expect($host1->hosting_manual)->toBeTrue()
        ->and($host2->hosting_manual)->toBeFalse();
});

test('host hides sensitive attributes in serialization', function () {
    $host = Host::factory()->create([
        'hash' => 'test_private_key',
        'hash_public' => 'test_public_key',
    ]);

    $array = $host->toArray();

    expect($array)->not->toHaveKey('hash')
        ->and($array)->not->toHaveKey('hash_public');
});

test('host has hostings relationship', function () {
    $host = Host::factory()->create();

    expect($host->hostings())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('host has users relationship', function () {
    $host = Host::factory()->create();

    expect($host->users())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('host has accounts relationship', function () {
    $host = Host::factory()->create();

    expect($host->accounts())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('host has domains through accounts relationship', function () {
    $host = Host::factory()->create();

    expect($host->domains())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

test('host to safe log array excludes sensitive data', function () {
    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
        'hash' => 'test_private_key',
        'hash_public' => 'test_public_key',
    ]);

    $safeArray = $host->toSafeLogArray();

    expect($safeArray)->toHaveKey('id')
        ->and($safeArray)->toHaveKey('fqdn', 'test.example.com')
        ->and($safeArray)->not->toHaveKey('hash')
        ->and($safeArray)->not->toHaveKey('hash_public');
});

test('host encrypts and decrypts hash correctly through full cycle', function () {
    $originalHash = '-----BEGIN OPENSSH PRIVATE KEY-----
MIIEpAIBAAKCAQEAwXYZ
-----END OPENSSH PRIVATE KEY-----';

    $host = Host::factory()->create(['hash' => $originalHash]);

    // Reload from database
    $host->refresh();

    // Should decrypt correctly
    expect($host->hash)->toBe($originalHash);
});

test('host factory creates valid host', function () {
    $host = Host::factory()->create();

    expect($host->id)->not->toBeNull()
        ->and($host->fqdn)->not->toBeNull()
        ->and($host->ip)->not->toBeNull()
        ->and($host->panel)->toBeInstanceOf(PanelType::class);
});

test('host soft deletes correctly', function () {
    $host = Host::factory()->create();
    $hostId = $host->id;

    $host->delete();

    // Should not find in normal queries
    expect(Host::find($hostId))->toBeNull();

    // Should find with trashed
    expect(Host::withTrashed()->find($hostId))->not->toBeNull();
});
