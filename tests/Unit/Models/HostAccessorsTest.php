<?php

declare(strict_types=1);

use App\Models\Host;
use Illuminate\Support\Facades\Crypt;

test('getHashAttribute returns empty string when value is null', function () {
    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash' => null]));

    expect($host->hash)->toBe('');
});

test('getHashAttribute returns empty string when value is empty string', function () {
    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash' => '']));

    expect($host->hash)->toBe('');
});

test('getHashAttribute decrypts encrypted value correctly', function () {
    $plaintext = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake-test-key-data\n-----END OPENSSH PRIVATE KEY-----"; // ggignore
    $encrypted = Crypt::encrypt($plaintext);

    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash' => $encrypted]));

    expect($host->hash)->toBe($plaintext);
});

test('getHashAttribute returns plaintext value when decryption fails but contains BEGIN OPENSSH PRIVATE KEY', function () {
    $plaintext = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake-test-key-data\n-----END OPENSSH PRIVATE KEY-----"; // ggignore

    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash' => $plaintext]));

    expect($host->hash)->toBe(trim($plaintext));
});

test('getHashAttribute returns empty string when decryption fails and value is not a key', function () {
    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash' => 'some-random-invalid-data']));

    expect($host->hash)->toBe('');
});

test('getHashPublicAttribute returns empty string when value is null', function () {
    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash_public' => null]));

    expect($host->hash_public)->toBe('');
});

test('getHashPublicAttribute decrypts encrypted value correctly', function () {
    $plaintext = 'ssh-ed25519 AAAAC3NzaFakeTestKeyData test@fake-host'; // ggignore
    $encrypted = Crypt::encrypt($plaintext);

    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash_public' => $encrypted]));

    expect($host->hash_public)->toBe($plaintext);
});

test('getHashPublicAttribute returns plaintext value when decryption fails but starts with ssh-', function () {
    $plaintext = 'ssh-ed25519 AAAAC3NzaFakeTestKeyData test@fake-host'; // ggignore

    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash_public' => $plaintext]));

    expect($host->hash_public)->toBe(trim($plaintext));
});

test('getHashPublicAttribute returns empty string when decryption fails and value is not a key', function () {
    $host = Host::factory()->make();
    $host->setRawAttributes(array_merge($host->getAttributes(), ['hash_public' => 'some-random-invalid-data']));

    expect($host->hash_public)->toBe('');
});

test('setHashPublicAttribute encrypts non-null non-empty value', function () {
    $plaintext = 'ssh-ed25519 AAAAC3NzaFakeTestKeyData test@fake-host'; // ggignore

    $host = Host::factory()->make();
    $host->hash_public = $plaintext;

    $rawValue = $host->getAttributes()['hash_public'];

    expect($rawValue)->not->toBe($plaintext);
    expect(Crypt::decrypt($rawValue))->toBe($plaintext);
});
