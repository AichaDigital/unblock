<?php

namespace App\Console\Commands;

use App\Models\Host;
use Exception;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class TestSshConnectionCommand extends Command
{
    protected $signature = 'test:ssh-connection {host_id}';

    protected $description = 'Test SSH connection to a host';

    public function handle()
    {
        $hostId = $this->argument('host_id');

        try {
            $host = Host::findOrFail($hostId);

            $this->info('=== HOST DATA ===');
            $this->line("ID: {$host->id}");
            $this->line("FQDN: {$host->fqdn}");
            $this->line("IP: {$host->ip}");
            $this->line("Port SSH: {$host->port_ssh}");
            $this->line("Admin: {$host->admin}");
            $this->line("Panel: {$host->panel}");
            $this->line('Hash exists: '.(! empty($host->hash) ? 'YES' : 'NO'));
            $this->line('Hash length: '.strlen($host->hash));

            $this->info("\n=== TESTING SSH CONNECTION ===");

            // Test connection with a configured port using Spatie/SSH
            $port = $host->port_ssh ?? 22;
            $this->line("Connecting to {$host->fqdn}:{$port} as root...");

            // Generate a temporary SSH key file
            $keyPath = base_path('.ssh/temp_key_'.time());
            file_put_contents($keyPath, $host->hash);
            chmod($keyPath, 0600);

            try {
                // Configurar multiplexing SSH
                $controlPath = '/tmp/cm/ssh_mux_%h_%p_%r';

                // Crear directorio para multiplexing si no existe
                if (! file_exists('/tmp/cm')) {
                    mkdir('/tmp/cm', 0755, true);
                }

                $ssh = Ssh::create('root', $host->fqdn, $port)
                    ->usePrivateKey($keyPath)
                    ->configureProcess(function ($process) use ($controlPath) {
                        // Configurar opciones de multiplexing SSH
                        $process->setEnv([
                            'SSH_MULTIPLEX_OPTIONS' => "-o ControlMaster=auto -o ControlPath=$controlPath -o ControlPersist=60s",
                        ]);

                        return $process;
                    });

                // Test CSF command
                $this->info('✅ SSH Connection successful!');
                $this->info("\n=== TESTING CSF COMMAND ===");

                $process = $ssh->execute('csf -g 2.2.2.2');
                $output = $process->getOutput();

                if ($process->getExitCode() !== 0) {
                    $this->error('❌ Command execution failed!');
                    $this->line('Error: '.$process->getErrorOutput());

                    return Command::FAILURE;
                }

                $this->info('✅ CSF command executed successfully!');
                $this->line('Output:');
                $this->line($output);

            } finally {
                // Clean up a temporary key file
                if (file_exists($keyPath)) {
                    unlink($keyPath);
                }
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
