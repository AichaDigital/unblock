<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\HqWhitelistMail;
use App\Models\{Host, User};
use App\Services\FirewallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, Mail, Storage};
use Throwable;

class ProcessHqWhitelistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $ip,
        public int $userId
    ) {}

    public function handle(FirewallService $firewallService): void
    {
        $hqHost = $this->resolveHqHost();
        if (! $hqHost) {
            Log::warning('HQ host not found, skipping HQ whitelist check');

            return;
        }

        if (empty($hqHost->hash)) {
            Log::warning('HQ host has no SSH private key (hash). Skipping HQ whitelist check', [
                'host_id' => $hqHost->id,
                'fqdn' => $hqHost->fqdn,
            ]);

            return;
        }

        $keyPath = '';
        try {
            $keyPath = $firewallService->generateSshKey($hqHost->hash);

            // SPECIAL CASE: Only check ModSecurity on HQ
            $modsecLogs = $this->checkModSecurityOnHq($firewallService, $hqHost, $keyPath, $this->ip);

            if (! $modsecLogs) {
                Log::info('IP not blocked on HQ host. No whitelist or email will be sent', [
                    'ip' => $this->ip,
                    'fqdn' => $hqHost->fqdn,
                ]);

                return;
            }

            // Whitelist temporarily (default 7200s as per config)
            $firewallService->checkProblems($hqHost, $keyPath, 'whitelist_hq', $this->ip);

            // Notify admin only if it was blocked on HQ and include modsec logs
            $this->notifyAdmin($hqHost, $modsecLogs);

            Log::info('HQ whitelist applied and user notified', [
                'ip' => $this->ip,
                'fqdn' => $hqHost->fqdn,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to process HQ whitelist job', [
                'ip' => $this->ip,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Best effort cleanup of temporary key file
            if ($keyPath) {
                try {
                    $fileName = basename($keyPath);
                    Storage::disk('ssh')->delete($fileName);
                } catch (Throwable) {
                }
            }
        }
    }

    private function resolveHqHost(): ?Host
    {
        $host = null;

        $hqHostId = config('unblock.hq.host_id');
        if ($hqHostId) {
            $host = Host::find($hqHostId);
            if ($host) {
                return $host;
            }
        }

        $hqFqdn = config('unblock.hq.fqdn');
        if ($hqFqdn) {
            $host = Host::where('fqdn', $hqFqdn)->first();
        }

        return $host;
    }

    private function checkModSecurityOnHq(FirewallService $firewallService, Host $host, string $keyPath, string $ip): bool|string
    {
        $modsec = $firewallService->checkProblems($host, $keyPath, 'mod_security_da', $ip);

        return $modsec ?: false;
    }

    private function notifyAdmin(Host $hqHost, string $modsecLogs): void
    {
        $adminEmail = config('unblock.admin_email');
        if (! $adminEmail) {
            return;
        }

        $adminUser = User::where('is_admin', true)->first() ?: new User([
            'name' => 'Admin',
            'email' => $adminEmail,
        ]);

        $ttl = (int) (config('unblock.hq.ttl') ?? 7200);

        Mail::to($adminEmail)->send(new HqWhitelistMail(
            user: $adminUser,
            ip: $this->ip,
            ttlSeconds: $ttl,
            hqHost: $hqHost,
            modsecLogs: $modsecLogs
        ));
    }
}
