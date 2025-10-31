# Phase 2: Code Snippets and Templates

Quick reference templates for creating Phase 2 Actions and Commands.

---

## Template 1: Simple Action (No Dependencies)

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class SyncHostsFromConfigAction
{
    use AsAction;

    /**
     * Handle hosts synchronization from configuration
     */
    public function handle(string $configPath): array
    {
        try {
            // Validation
            if (! file_exists($configPath)) {
                throw new \RuntimeException("Config file not found: {$configPath}");
            }

            // Processing
            $config = json_decode(file_get_contents($configPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON in config file");
            }

            // Return result
            return [
                'success' => true,
                'message' => 'Hosts synced successfully',
                'count' => count($config),
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sync hosts from config failed', [
                'config_path' => $configPath,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to sync hosts',
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

---

## Template 2: Complex Action with Dependencies

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\FirewallException;
use App\Models\Host;
use App\Services\SshConnectionManager;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateHostConnectionAction
{
    use AsAction;

    public function __construct(
        protected SshConnectionManager $sshManager
    ) {}

    /**
     * Validate SSH connection to a host
     */
    public function handle(Host $host): array
    {
        try {
            // Create session
            $session = $this->sshManager->createSession($host);

            // Test connection
            $result = $session->execute('whoami');

            if (trim($result) !== 'root') {
                throw new \RuntimeException("Expected root user, got: {$result}");
            }

            $session->cleanup();

            Log::info('Host connection validated', [
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
            ]);

            return [
                'success' => true,
                'message' => 'Connection validated successfully',
                'user' => trim($result),
            ];

        } catch (\Exception $e) {
            Log::error('Host connection validation failed', [
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'error' => $e->getMessage(),
            ]);

            throw new FirewallException(
                "Failed to validate connection to {$host->fqdn}",
                hostName: $host->fqdn,
                previous: $e
            );
        }
    }
}
```

---

## Template 3: Async Action Dispatching Job

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SyncHostsJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncHostsAction
{
    use AsAction;

    /**
     * Dispatch async hosts sync job
     */
    public function handle(User $user, string $source = 'config'): array
    {
        try {
            // Validate user permission
            if (! $user->is_admin) {
                throw new \Exception('Only admins can sync hosts');
            }

            // Dispatch job for async processing
            SyncHostsJob::dispatch(
                userId: $user->id,
                source: $source
            );

            Log::info('Hosts sync job dispatched', [
                'user_id' => $user->id,
                'source' => $source,
            ]);

            return [
                'success' => true,
                'message' => 'Sync job started, check logs for progress',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to dispatch sync job', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to start sync',
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

---

## Template 4: Panel-Specific Action

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Host;
use App\Services\FirewallService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncHostMetadataAction
{
    use AsAction;

    public function __construct(
        protected FirewallService $firewallService
    ) {}

    /**
     * Sync host metadata based on panel type
     */
    public function handle(Host $host): array
    {
        try {
            $metadata = match ($host->panel) {
                'cpanel' => $this->syncCpanelMetadata($host),
                'directadmin', 'da' => $this->syncDirectAdminMetadata($host),
                default => throw new \InvalidArgumentException("Unsupported panel: {$host->panel}"),
            };

            Log::info('Host metadata synced', [
                'host_id' => $host->id,
                'panel' => $host->panel,
                'metadata_count' => count($metadata),
            ]);

            return [
                'success' => true,
                'message' => 'Metadata synced successfully',
                'metadata' => $metadata,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to sync host metadata', [
                'host_id' => $host->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to sync metadata',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function syncCpanelMetadata(Host $host): array
    {
        // cPanel-specific logic
        return ['panel' => 'cpanel', 'version' => '1.0'];
    }

    private function syncDirectAdminMetadata(Host $host): array
    {
        // DirectAdmin-specific logic
        return ['panel' => 'directadmin', 'version' => '1.0'];
    }
}
```

---

## Template 5: Simple Command

```php
<?php

namespace App\Console\Commands;

use App\Actions\SyncHostsFromConfigAction;
use Exception;
use Illuminate\Console\Command;

class SyncHostsFromConfigCommand extends Command
{
    protected $signature = 'sync:hosts-from-config
                           {config : Path to configuration file}
                           {--debug : Enable debug output}';

    protected $description = 'Synchronize hosts from configuration file';

    public function handle(): int
    {
        $configPath = $this->argument('config');
        $debug = $this->option('debug');

        try {
            $action = new SyncHostsFromConfigAction();
            $result = $action->handle($configPath);

            if ($result['success']) {
                $this->info('Hosts synced successfully');
                $this->line("Synced: {$result['count']} hosts");
                return Command::SUCCESS;
            } else {
                $this->error('Failed to sync hosts: ' . $result['message']);
                if (isset($result['error'])) {
                    $this->error('Error: ' . $result['error']);
                }
                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            if ($debug) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}
```

---

## Template 6: Complex Interactive Command

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\{confirm, select, text, info, error, table};

class SyncValidateConnectionsCommand extends Command
{
    protected $signature = 'sync:validate-connections
                           {--all : Validate all hosts}
                           {--host-id= : Specific host ID}
                           {--panel= : Specific panel (cpanel|directadmin)}';

    protected $description = 'Validate SSH connections to hosts';

    public function handle(): int
    {
        info('Host Connection Validation');

        // Determine which hosts to validate
        $hosts = $this->selectHosts();

        if ($hosts->isEmpty()) {
            error('No hosts selected');
            return Command::FAILURE;
        }

        // Validate each host
        $passed = 0;
        $failed = 0;

        foreach ($hosts as $host) {
            $this->line("Validating {$host->fqdn}...");

            try {
                // Test connection logic here
                $result = $this->testConnection($host);

                if ($result) {
                    $passed++;
                    $this->line("  ✓ Success");
                } else {
                    $failed++;
                    $this->line("  ✗ Failed");
                }

            } catch (\Exception $e) {
                $failed++;
                $this->line("  ✗ Error: " . $e->getMessage());
            }
        }

        // Summary
        info("Validation Complete: {$passed} passed, {$failed} failed");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function selectHosts(): Collection
    {
        if ($this->option('host-id')) {
            return Host::where('id', $this->option('host-id'))->get();
        }

        if ($this->option('all')) {
            return Host::all();
        }

        if ($this->option('panel')) {
            return Host::where('panel', $this->option('panel'))->get();
        }

        // Interactive selection
        $host = $this->selectHost();
        return $host ? collect([$host]) : collect();
    }

    private function selectHost(): ?Host
    {
        $hosts = Host::whereNull('deleted_at')->orderBy('fqdn')->get();

        if ($hosts->isEmpty()) {
            error('No hosts available');
            return null;
        }

        $options = $hosts->mapWithKeys(fn ($host) => [
            $host->id => "{$host->fqdn} ({$host->panel})",
        ])->toArray();

        $selectedId = select('Select host to validate:', $options);
        return $hosts->find($selectedId);
    }

    private function testConnection(Host $host): bool
    {
        // Implement actual connection test
        return true;
    }
}
```

---

## Template 7: Async Job

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Host;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncHostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $source = 'config'
    ) {}

    /**
     * Execute the job
     */
    public function handle(): void
    {
        Log::info('Starting hosts sync job', [
            'user_id' => $this->userId,
            'source' => $this->source,
        ]);

        try {
            $hosts = $this->getHostsToSync();

            foreach ($hosts as $host) {
                $this->syncHost($host);
            }

            Log::info('Hosts sync job completed', [
                'synced_count' => $hosts->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Hosts sync job failed', [
                'error' => $e->getMessage(),
                'source' => $this->source,
            ]);

            throw $e;
        }
    }

    private function getHostsToSync(): \Illuminate\Support\Collection
    {
        // Implement logic to fetch hosts to sync
        return Host::all();
    }

    private function syncHost(Host $host): void
    {
        try {
            // Sync individual host
            Log::debug('Syncing host', ['host_id' => $host->id]);

        } catch (Exception $e) {
            Log::error('Failed to sync host', [
                'host_id' => $host->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

---

## Template 8: Action with AsCommand Trait (Dual-Mode)

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class SyncHostsAction
{
    use AsAction;
    use AsCommand;

    /**
     * Command signature for CLI usage
     */
    public string $commandSignature = 'sync:hosts
                                      {--debug : Enable debug output}
                                      {--dry-run : Show what would be done}
                                      {--force : Force sync even if recent}';

    /**
     * Command description for CLI usage
     */
    public string $commandDescription = 'Synchronize all configured hosts';

    /**
     * Handle the sync process (works as both Action and Command)
     */
    public function handle(?Command $command = null): void
    {
        $debug = ($command?->option('debug') ?? false) || app()->environment('local');
        $dryRun = $command?->option('dry-run') ?? false;
        $force = $command?->option('force') ?? false;

        if ($command) {
            $command->info('Starting host synchronization...');
        }

        try {
            // Process sync logic here
            $this->sync($debug, $dryRun, $force);

            if ($command) {
                $command->info('Synchronization completed successfully');
            }

        } catch (\Exception $e) {
            if ($command) {
                $command->error('Synchronization failed: ' . $e->getMessage());
            }

            throw $e;
        }
    }

    private function sync(bool $debug, bool $dryRun, bool $force): void
    {
        // Implementation
    }
}
```

---

## Key Patterns Summary

### SSH Operations Pattern
```php
// Create session
$session = $sshManager->createSession($host);

try {
    // Execute commands
    $output = $session->execute('csf -g 192.168.1.1');
} finally {
    // Always cleanup
    $session->cleanup();
}
```

### Panel Detection Pattern
```php
if ($host->panel === 'directadmin' || $host->panel === 'da') {
    // DirectAdmin-specific
} else if ($host->panel === 'cpanel') {
    // cPanel-specific
}
```

### Job Dispatch Pattern
```php
MyJob::dispatch(
    param1: $value1,
    param2: $value2
)->delay(now()->addMinutes(5));
```

### Database Transaction Pattern
```php
DB::transaction(function () {
    $host->update(['status' => 'syncing']);
    // Other operations
}, attempts: 3);
```

### Error Handling Pattern
```php
try {
    // Operation
} catch (Exception $e) {
    Log::error('Operation failed', [
        'host_id' => $host->id,
        'error' => $e->getMessage(),
    ]);

    throw new FirewallException(
        "Operation failed for {$host->fqdn}",
        hostName: $host->fqdn,
        previous: $e
    );
}
```

---

**Document Version:** 1.0
**Last Updated:** 2025-10-28
