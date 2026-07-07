<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\SshKeyGenerator;
use Illuminate\Support\Facades\Process;

describe('SshKeyGenerator', function () {
    beforeEach(function () {
        $this->generator = new SshKeyGenerator;
    });

    it('can generate SSH keys for a host', function () {
        $host = Host::factory()->create([
            'hash' => null,
            'hash_public' => null,
        ]);

        $result = $this->generator->generateForHost($host);

        expect($result)->toHaveKey('success', true)
            ->and($result)->toHaveKey('message')
            ->and($result)->toHaveKey('public_key');

        $host->refresh();

        expect($host->hash)->not->toBeEmpty()
            ->and($host->hash_public)->not->toBeEmpty()
            ->and($host->hash)->toContain('BEGIN OPENSSH PRIVATE KEY')
            ->and($host->hash_public)->toStartWith('ssh-ed25519');
    });

    it('replaces existing SSH keys when generating new ones', function () {
        $host = Host::factory()->create([
            'hash' => 'old_private_key',
            'hash_public' => 'old_public_key',
        ]);

        $result = $this->generator->generateForHost($host);

        expect($result['success'])->toBeTrue();

        $host->refresh();

        expect($host->hash)->not->toBe('old_private_key')
            ->and($host->hash_public)->not->toBe('old_public_key');
    });

    it('checks if host has SSH keys', function () {
        $hostWithKeys = Host::factory()->create([
            'hash' => '-----BEGIN OPENSSH PRIVATE KEY-----',
            'hash_public' => 'ssh-ed25519 AAAA...',
        ]);

        $hostWithoutKeys = Host::factory()->create([
            'hash' => null,
            'hash_public' => null,
        ]);

        expect($this->generator->hasKeys($hostWithKeys))->toBeTrue()
            ->and($this->generator->hasKeys($hostWithoutKeys))->toBeFalse();
    });

    it('returns an error when ssh-keygen fails', function () {
        Process::fake([
            'ssh-keygen*' => Process::result(errorOutput: 'ssh-keygen: error', exitCode: 1),
        ]);

        $host = Host::factory()->create([
            'hash' => null,
            'hash_public' => null,
        ]);

        $result = $this->generator->generateForHost($host);

        expect($result)->toHaveKey('success', false)
            ->and($result['message'])->toBe('Failed to generate SSH keys')
            ->and($result['error'])->toContain('ssh-keygen: error');

        $host->refresh();

        expect($host->hash)->toBeEmpty()
            ->and($host->hash_public)->toBeEmpty();
    });

    it('throws an exception when ssh-keygen fails while generating keys for a fqdn', function () {
        Process::fake([
            'ssh-keygen*' => Process::result(errorOutput: 'ssh-keygen: error', exitCode: 1),
        ]);

        expect(fn () => $this->generator->generateForFqdn('server.example.com'))
            ->toThrow(Exception::class, 'Failed to generate SSH keys: ssh-keygen: error');
    });

    it('returns an error when generated key files cannot be read', function () {
        // Faked process succeeds but never writes key files to disk
        Process::fake();

        $host = Host::factory()->create([
            'hash' => null,
            'hash_public' => null,
        ]);

        $result = $this->generator->generateForHost($host);

        expect($result)->toHaveKey('success', false)
            ->and($result['message'])->toBe('Failed to read generated keys');
    });
});
