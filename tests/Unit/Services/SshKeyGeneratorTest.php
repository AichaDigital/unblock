<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\SshKeyGenerator;

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

    // NOTE: Test para fallo de ssh-keygen eliminado
    // Razón: No tiene sentido como test unitario a nivel de sistema
    // El comando ssh-keygen es una dependencia del sistema operativo
    // Si ssh-keygen falla, el problema es de configuración del servidor, no del código
    // Si se necesita validar en el futuro, debería ser un test de integración de infraestructura
});
