<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use App\Services\SshKeyGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{File, Process};

use function Laravel\Prompts\{error, info, select};

class AddHostKeyCommand extends Command
{
    protected $signature = 'add:host-key {hostId?} {--generate : Generate SSH keys for the host}';

    protected $description = 'Add SSH host key to known_hosts and optionally generate SSH keys for a given host';

    public function handle(): void
    {
        $hostId = $this->argument('hostId');
        $generateKeys = $this->option('generate');

        // Si no se proporciona hostId, permitir seleccionar con prompts
        if (! $hostId) {
            $host = $this->selectHost();
            if (! $host instanceof Host) {
                error('No se seleccionó ningún host.');

                return;
            }
            $hosts = collect([$host]);
        } else {
            $hosts = Host::where('id', $hostId)->get();
        }

        if ($hosts->isEmpty()) {
            error('No hosts found.');

            return;
        }

        foreach ($hosts as $host) {
            info("Procesando host: {$host->fqdn}");

            if ($generateKeys) {
                $this->generateSshKeys($host);
            }

            $this->processHost($host);
        }
    }

    private function processHost(Host $host): void
    {
        $knownHostsPath = (string) config('unblock.ssh.known_hosts_path');
        $this->removeExistingHostKey($knownHostsPath, $host->ip, $host->fqdn);

        // Command to fetch the host key
        $command = sprintf('ssh-keyscan -p %d %s', $host->port_ssh, $host->ip);

        $result = Process::run($command);

        if (! $result->successful()) {
            $this->error('Failed to fetch host key for '.$host->fqdn.': '.$result->errorOutput());

            return;
        }

        // Append the host key to known_hosts
        File::append($knownHostsPath, $result->output());

        $host->update([
            'ssh_host_key_verified' => true,
            'ssh_host_key_verified_at' => now(),
        ]);

        $this->info("Host key added to known_hosts for {$host->fqdn}.");
    }

    private function removeExistingHostKey(string $knownHostsPath, string $ip, string $fqdn): void
    {
        if (! File::exists($knownHostsPath)) {
            return;
        }

        $filteredLines = File::lines($knownHostsPath)
            ->filter(function (string $line) use ($ip, $fqdn) {
                return $line !== '' && ! str_contains($line, $ip) && ! str_contains($line, $fqdn);
            });

        File::put($knownHostsPath, $filteredLines->implode(PHP_EOL).PHP_EOL);
    }

    private function selectHost(): ?Host
    {
        $hosts = Host::whereNull('deleted_at')->orderBy('fqdn')->get();

        if ($hosts->isEmpty()) {
            error('No hay hosts disponibles');

            return null;
        }

        $options = $hosts->mapWithKeys(fn ($host) => [
            $host->id => "ID: {$host->id} | {$host->fqdn}:{$host->port_ssh} ({$host->panel->value})",
        ])->toArray();

        $selectedId = select('Selecciona el host:', $options);

        return $hosts->find($selectedId);
    }

    private function generateSshKeys(Host $host): void
    {
        info("Generando claves SSH para {$host->fqdn}...");

        $generator = new SshKeyGenerator;
        $result = $generator->generateForHost($host);

        if ($result['success']) {
            info('✅ Claves SSH generadas y guardadas en la base de datos');
            info('🔑 Clave pública para añadir al servidor remoto:');
            $this->line($result['public_key']);
        } else {
            error($result['message']);
            if (isset($result['error'])) {
                error($result['error']);
            }
        }
    }
}
