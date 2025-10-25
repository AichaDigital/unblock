<?php

namespace App\Console\Commands;

use App\Actions\UnblockIpAction;
use Exception;
use Illuminate\Console\Command;

class UnblockIpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unblock:ip
                           {ip : IP address to unblock}
                           {--host= : Specific host ID to unblock from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unblock an IP address from firewall and clear rate limiting records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ip = $this->argument('ip');
        $hostId = $this->option('host');

        try {
            // If no specific host provided, show error
            if (! $hostId) {
                $this->error('Host ID is required. Use --host=ID option');
                $this->line('Example: php artisan unblock:ip 192.168.1.1 --host=1');

                return Command::FAILURE;
            }

            $action = new UnblockIpAction(app('App\Services\FirewallService'));
            $result = $action->handle($ip, (int) $hostId, 'default');

            if ($result['success']) {
                $this->info("IP {$ip} has been successfully unblocked from host {$hostId}");

                return Command::SUCCESS;
            } else {
                $this->error("Failed to unblock IP {$ip}: ".$result['message']);
                if (isset($result['error'])) {
                    $this->error('Error details: '.$result['error']);
                }

                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $this->error("Failed to unblock IP {$ip}: ".$e->getMessage());

            return Command::FAILURE;
        }
    }
}
