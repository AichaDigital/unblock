<?php

declare(strict_types=1);

use App\Models\Host;

describe('AddHostKeyCommand', function () {
    it('can generate SSH keys for a host with --generate option', function () {
        $host = Host::factory()->create([
            'hash' => null,
            'hash_public' => null,
        ]);

        $this->artisan('add:host-key', [
            'hostId' => $host->id,
            '--generate' => true,
        ])
            ->assertSuccessful();

        $host->refresh();

        expect($host->hash)->not->toBeEmpty()
            ->and($host->hash_public)->not->toBeEmpty()
            ->and($host->hash)->toContain('BEGIN OPENSSH PRIVATE KEY')
            ->and($host->hash_public)->toStartWith('ssh-ed25519');
    });

    it('handles non-existent host gracefully', function () {
        $this->artisan('add:host-key', [
            'hostId' => 999999,
            '--generate' => true,
        ])
            ->assertSuccessful(); // Command doesn't fail but returns early with error message
    });

    it('can process host without generating keys', function () {
        $host = Host::factory()->create([
            'ip' => '127.0.0.1',
            'port_ssh' => 22,
        ]);

        // This test will try to fetch SSH keys from localhost
        // It may fail if SSH is not running locally, but it tests the command flow
        $this->artisan('add:host-key', [
            'hostId' => $host->id,
        ])
            ->expectsOutput("Procesando host: {$host->fqdn}")
            ->assertSuccessful();
    })->skip('Requires SSH server running on localhost');
});
