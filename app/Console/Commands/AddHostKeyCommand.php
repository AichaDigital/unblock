<?php

namespace App\Console\Commands;

use App\Models\Host;
use App\Services\SshKeyGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

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
            if (! $host) {
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
        $knownHostsPath = getenv('HOME').'/.ssh/known_hosts';
        $this->removeExistingHostKey($knownHostsPath, $host->ip, $host->fqdn);

        // Command to fetch the host key
        $command = sprintf('ssh-keyscan -p %d %s', $host->port_ssh, $host->ip);

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to fetch host key for '.$host->fqdn.': '.$process->getErrorOutput());

            return;
        }

        // Append the host key to known_hosts
        file_put_contents(
            $knownHostsPath,
            $process->getOutput(),
            FILE_APPEND
        );

        $this->info("Host key added to known_hosts for {$host->fqdn}.");
    }

    private function removeExistingHostKey($knownHostsPath, $ip, $fqdn): void
    {
        if (! file_exists($knownHostsPath)) {
            return;
        }

        $lines = file($knownHostsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filteredLines = array_filter($lines, function ($line) use ($ip, $fqdn) {
            return ! str_contains($line, $ip) && ! str_contains($line, $fqdn);
        });

        file_put_contents($knownHostsPath, implode(PHP_EOL, $filteredLines).PHP_EOL);
    }

    private function selectHost(): ?Host
    {
        $hosts = Host::whereNull('deleted_at')->orderBy('fqdn')->get();

        if ($hosts->isEmpty()) {
            error('No hay hosts disponibles');

            return null;
        }

        $options = $hosts->mapWithKeys(fn ($host) => [
            $host->id => "ID: {$host->id} | {$host->fqdn}:{$host->port_ssh} ({$host->panel})",
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
