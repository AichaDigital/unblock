# Phase 2: Sync System with Actions and Commands - Implementation Guide

## Document Purpose
This document provides a comprehensive guide for implementing Phase 2 of the unblock system, detailing the codebase architecture, patterns, and conventions discovered through thorough exploration.

---

## 1. SSH Connection Infrastructure

### 1.1 SshConnectionManager
**File:** `/Users/abkrim/SitesLR12/unblock/app/Services/SshConnectionManager.php`

The SSH connection system uses the Spatie SSH library with connection multiplexing for efficiency.

**Key Methods:**
- `generateSshKey(string $hash): string` - Creates temporary SSH key file with normalized line endings
- `executeCommand(Host $host, string $sshKeyPath, string $command): string` - Executes command via SSH
- `removeSshKey(string $keyPath): void` - Cleans up SSH key file
- `createSession(Host $host): SshSession` - Creates session for multiple commands
- `prepareMultiplexingPath(): void` - Sets up `/tmp/cm` directory for SSH multiplexing

**Important Implementation Details:**
```php
// SSH key normalization (critical for key validation)
$normalizedHash = str_replace(["\r\n", "\r"], "\n", $hash);
if (! str_ends_with($normalizedHash, "\n")) {
    $normalizedHash .= "\n";
}

// SSH multiplexing configuration
$controlPath = '/tmp/cm/ssh_mux_%h_%p_%r';
$ssh = Ssh::create('root', $host->fqdn, $port)
    ->usePrivateKey($sshKeyPath)
    ->configureProcess(function ($process) use ($controlPath) {
        $process->setEnv([
            'SSH_MULTIPLEX_OPTIONS' => "-o ControlMaster=auto -o ControlPath=$controlPath -o ControlPersist=60s",
        ]);
        return $process;
    });
```

### 1.2 SshSession
**File:** `/Users/abkrim/SitesLR12/unblock/app/Services/SshSession.php`

Manages a single SSH session with automatic cleanup and comprehensive logging.

**Key Methods:**
- `execute(string $command): string` - Executes command with logging
- `getSshKeyPath(): string` - Gets SSH key path
- `getHost(): Host` - Returns the host
- `cleanup(): void` - Cleans up resources
- `__destruct()` - Automatic cleanup on destruction

**Usage Pattern:**
```php
$session = $sshConnectionManager->createSession($host);
try {
    $output = $session->execute('csf -g 192.168.1.1');
    // Process output
} finally {
    $session->cleanup();
}
```

### 1.3 FirewallService
**File:** `/Users/abkrim/SitesLR12/unblock/app/Services/FirewallService.php`

Direct SSH execution wrapper with command building and output processing.

**Available Commands:**
```php
'csf' => "csf -g {$ip}",
'csf_deny_check' => "cat /etc/csf/csf.deny | grep {$ip_escaped} || true",
'csf_tempip_check' => "cat /var/lib/csf/csf.tempip | grep {$ip_escaped} || true",
'mod_security_da' => "cat /var/log/nginx/modsec_audit.log | grep {$ip_escaped} || true",
'exim_directadmin' => "cat /var/log/exim/mainlog | grep -Ea {$ip_escaped} | grep 'authenticator failed'",
'dovecot_directadmin' => "cat /var/log/mail.log | grep -Ea {$ip_escaped} | grep 'auth failed'",
'da_bfm_check' => "cat /usr/local/directadmin/data/admin/ip_blacklist | grep -E '^{$ip_escaped}(\\s|\$)' || true",
'da_bfm_remove' => "sed -i '/^{$ip_escaped}(\\s|\$)/d' /usr/local/directadmin/data/admin/ip_blacklist",
'da_bfm_whitelist_add' => "echo '{$ip}' >> /usr/local/directadmin/data/admin/ip_whitelist",
'unblock' => "csf -dr {$ip} && csf -tr {$ip} && csf -ta {$ip} 86400",
'whitelist' => "csf -ta {$ip} 86400",
```

### 1.4 Host Model SSH Key Storage
**File:** `/Users/abkrim/SitesLR12/unblock/app/Models/Host.php`

SSH keys are encrypted using Laravel's `Crypt` facade with automatic decryption via accessors.

```php
// Fillable attributes for SSH
'hash' => 'private SSH key (encrypted)',
'hash_public' => 'public SSH key (encrypted)',
'port_ssh' => 'SSH port (default: 22)',

// Encryption/Decryption
public function setHashAttribute($value): void {
    if (! is_null($value) && $value !== '') {
        $this->attributes['hash'] = Crypt::encrypt($value);
    }
}

public function getHashAttribute($value): string {
    // Attempts decryption, falls back to plaintext for legacy data
    return Crypt::decrypt($value) ?? $value;
}
```

---

## 2. Existing Actions Pattern

### 2.1 Base Action Structure
**Using:** Lorisleiva Actions library with `AsAction` trait

**Pattern Requirements:**
```php
namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class MyAction
{
    use AsAction;

    public function handle(/* parameters */): /* return type */
    {
        // Implementation
    }
}
```

### 2.2 Real Examples

#### UnblockIpAction
**File:** `/Users/abkrim/SitesLR12/unblock/app/Actions/UnblockIpAction.php`

Simple action that handles IP unblocking with panel-specific logic.

```php
class UnblockIpAction
{
    use AsAction;

    public function __construct(
        protected FirewallService $firewallService
    ) {}

    public function handle(string $ip, int $hostId, string $keyName): array
    {
        try {
            $host = Host::findOrFail($hostId);

            // Standard CSF unblock
            $this->firewallService->checkProblems($host, $keyName, 'unblock', $ip);

            // For DirectAdmin servers, also remove from BFM blacklist
            if ($host->panel === 'directadmin' || $host->panel === 'da') {
                try {
                    $bfmCheck = $this->firewallService->checkProblems($host, $keyName, 'da_bfm_check', $ip);
                    if (! empty(trim($bfmCheck))) {
                        $this->firewallService->checkProblems($host, $keyName, 'da_bfm_remove', $ip);
                    }
                } catch (Throwable $bfmException) {
                    Log::warning('Failed to remove IP from DirectAdmin BFM blacklist', [
                        'ip' => $ip,
                        'host' => $host->fqdn,
                        'error' => $bfmException->getMessage(),
                    ]);
                }
            }

            return ['success' => true, 'message' => __('messages.firewall.ip_unblocked')];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => __('messages.firewall.unblock_failed'), 'error' => $e->getMessage()];
        }
    }
}
```

#### CheckFirewallAction
**File:** `/Users/abkrim/SitesLR12/unblock/app/Actions/CheckFirewallAction.php`

Orchestrator action that delegates to specialized services (Single Responsibility Pattern).

```php
class CheckFirewallAction
{
    use AsAction;

    public function handle(string $ip, int $userId, int $hostId, ?int $copyUserId = null, ?string $develop = null): array
    {
        try {
            // Validate and load models
            $user = $this->loadUser($userId);
            $host = $this->loadHost($hostId);
            $this->validateIpAddress($ip);
            $this->validateUserAccess($user, $host);

            // Dispatch job (non-blocking)
            ProcessFirewallCheckJob::dispatch(
                ip: $ip,
                userId: $userId,
                hostId: $hostId,
                copyUserId: $copyUserId
            );

            // Always dispatch HQ whitelist check in parallel
            ProcessHqWhitelistJob::dispatch(ip: $ip, userId: $userId);

            return ['success' => true, 'message' => __('messages.firewall.check_started')];
        } catch (Exception $e) {
            throw new FirewallException("Failed to start firewall check for IP {$ip}", previous: $e);
        }
    }

    private function loadUser(int $userId): User { /* ... */ }
    private function loadHost(int $hostId): Host { /* ... */ }
    private function validateIpAddress(string $ip): void { /* ... */ }
    private function validateUserAccess(User $user, Host $host): void { /* ... */ }
}
```

#### SimpleUnblockAction
**File:** `/Users/abkrim/SitesLR12/unblock/app/Actions/SimpleUnblockAction.php`

Complex orchestrator with rate limiting and validation.

```php
class SimpleUnblockAction
{
    use AsAction;

    public function handle(string $ip, string $domain, string $email): void
    {
        // Normalize domain
        $normalizedDomain = $this->normalizeDomain($domain);

        // Multi-vector rate limiting
        $this->checkEmailRateLimit($email);
        $this->checkDomainRateLimit($normalizedDomain);

        // Find blocked host
        $hosts = Host::all();
        $blockedHost = $this->findHostWithBlockedIp($ip, $hosts);

        if (! $blockedHost) {
            $this->notifyAdminSilentAttempt($ip, $normalizedDomain, $email, 'ip_not_blocked');
            return;
        }

        // Dispatch job for async processing
        ProcessSimpleUnblockJob::dispatch(
            ip: $ip,
            domain: $normalizedDomain,
            email: $email,
            hostId: $blockedHost->id
        );

        // Activity logging (GDPR compliant)
        activity()
            ->withProperties([/* ... */])
            ->log('simple_unblock_request');
    }
}
```

#### WhmcsSynchro
**File:** `/Users/abkrim/SitesLR12/unblock/app/Actions/WhmcsSynchro.php`

Action with both `AsAction` and `AsCommand` traits (dual-mode).

```php
class WhmcsSynchro
{
    use AsAction;
    use AsCommand;

    public string $commandSignature = 'whmcs:sync
                                      {--debug : Enable debug mode with detailed output}
                                      {--user= : Process only specific user by email}
                                      {--dry-run : Show what would be done without making changes}';

    public string $commandDescription = 'Synchronize users and hostings from WHMCS';

    public function handle(Command|null $command): void
    {
        // Can be called as Action or Command
    }
}
```

---

## 3. Existing Commands Pattern

### 3.1 Base Command Structure
**Extends:** `Illuminate\Console\Command`

**Naming Convention:** `{Action}Command` or `{Verb}{Noun}Command`

### 3.2 Real Examples

#### UnblockIpCommand
**File:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/UnblockIpCommand.php`

Simple command calling an Action.

```php
class UnblockIpCommand extends Command
{
    protected $signature = 'unblock:ip
                           {ip : IP address to unblock}
                           {--host= : Specific host ID to unblock from}';

    protected $description = 'Unblock an IP address from firewall and clear rate limiting records';

    public function handle(): int
    {
        $ip = $this->argument('ip');
        $hostId = $this->option('host');

        if (! $hostId) {
            $this->error('Host ID is required. Use --host=ID option');
            return Command::FAILURE;
        }

        try {
            $action = new UnblockIpAction(app('App\Services\FirewallService'));
            $result = $action->handle($ip, (int) $hostId, 'default');

            if ($result['success']) {
                $this->info("IP {$ip} has been successfully unblocked from host {$hostId}");
                return Command::SUCCESS;
            } else {
                $this->error("Failed to unblock IP {$ip}: " . $result['message']);
                return Command::FAILURE;
            }
        } catch (Exception $e) {
            $this->error("Failed to unblock IP {$ip}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

#### UserCreateCommand
**File:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/UserCreateCommand.php`

Complex command with interactive and non-interactive modes.

```php
class UserCreateCommand extends Command
{
    protected $signature = 'user:create
                            {--no-secure : Disable complex password requirements (development only)}
                            {--admin : Create an admin user instead of normal user}
                            {--email= : Email address for the user}
                            {--first-name= : First name for the user}
                            {--last-name= : Last name for the user}
                            {--company-name= : Company name for the user (optional)}
                            {--password= : Password for the user}';

    public function handle(): int
    {
        // Determine mode
        $isNonInteractive = $this->option('email') && 
                           $this->option('first-name') && 
                           $this->option('last-name') && 
                           $this->option('password');

        if ($isNonInteractive) {
            return $this->handleNonInteractive();
        }

        return $this->handleInteractive();
    }

    private function handleNonInteractive(): int { /* ... */ }
    private function handleInteractive(): int { /* ... */ }
}
```

#### TestHostConnectionCommand (Development)
**File:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/Develop/TestHostConnectionCommand.php`

Development diagnostic command with multiple sub-operations.

```php
class TestHostConnectionCommand extends Command
{
    protected $signature = 'develop:test-host-connection {--host-id= : ID específico del host a probar}';

    public function handle(): int
    {
        $host = $this->selectHost();
        $keyFile = $this->diagnoseKey($host);
        $this->testConnection($host, $keyFile);
        $this->showDebugInfo($keyFile);
        return 0;
    }

    private function selectHost(): ?Host { /* ... */ }
    private function diagnoseKey(Host $host): string { /* ... */ }
    private function testConnection(Host $host, string $existingKeyFile = ''): void { /* ... */ }
    private function showDebugInfo(string $keyFile): void { /* ... */ }
}
```

### 3.3 Command Output Handling
**Using:** `Laravel\Prompts` for interactive prompts

```php
use function Laravel\Prompts\{confirm, password, text, select, table, warning, error, info};

// Text input
$value = text(label: 'Label', placeholder: 'placeholder', required: true);

// Select dropdown
$value = select('Choose:', ['option1' => 'Label 1', 'option2' => 'Label 2']);

// Confirmation
$confirmed = confirm(label: 'Continue?', default: false);

// Password (hidden)
$pwd = password(label: 'Password:', required: true);

// Table display
table(['Column 1', 'Column 2'], [['value1', 'value2']]);

// Info/warning/error messages
info('Information message');
warning('Warning message');
error('Error message');
```

---

## 4. Panel-Specific Logic

### 4.1 Panel Detection
**Supported Panels:** `cpanel`, `directadmin`, `da`

**Detection Pattern:**
```php
// In Host model
$host->panel; // 'cpanel' or 'directadmin'

// Common check pattern
if ($host->panel === 'directadmin' || $host->panel === 'da') {
    // DirectAdmin-specific logic
} else if ($host->panel === 'cpanel') {
    // cPanel-specific logic
}

// Factory Pattern
$analyzer = $analyzerFactory->createForHost($host);
```

### 4.2 Firewall Analyzer Factory
**File:** `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/FirewallAnalyzerFactory.php`

Factory pattern for creating panel-specific analyzers.

```php
class FirewallAnalyzerFactory
{
    private array $analyzers = [
        'directadmin' => DirectAdminFirewallAnalyzer::class,
        'cpanel' => CpanelFirewallAnalyzer::class,
    ];

    public function createForHost(Host $host): FirewallAnalyzerInterface
    {
        if (! isset($this->analyzers[$host->panel])) {
            throw new InvalidArgumentException(
                "No analyzer available for panel type: {$host->panel}"
            );
        }

        $analyzerClass = $this->analyzers[$host->panel];
        return new $analyzerClass($this->firewallService, $host);
    }

    public function registerAnalyzer(string $panelType, string $analyzerClass): void
    {
        if (! is_subclass_of($analyzerClass, FirewallAnalyzerInterface::class)) {
            throw new InvalidArgumentException(
                'Analyzer class must implement FirewallAnalyzerInterface'
            );
        }

        $this->analyzers[$panelType] = $analyzerClass;
    }
}
```

### 4.3 CPanel Analyzer
**File:** `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/CpanelFirewallAnalyzer.php`

cPanel-specific service checks:
- CSF (ConfigServer Firewall)
- CSF specials/whitelist
- Exim mail server
- Dovecot mail server

### 4.4 DirectAdmin Analyzer
**File:** `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/DirectAdminFirewallAnalyzer.php`

DirectAdmin-specific service checks:
- CSF (ConfigServer Firewall)
- DirectAdmin BFM (Brute Force Monitor) blacklist/whitelist
- Exim mail server
- Dovecot mail server
- ModSecurity WAF

**DirectAdmin-Specific File Paths:**
```
/usr/local/directadmin/data/admin/ip_blacklist
/usr/local/directadmin/data/admin/ip_whitelist
/var/log/nginx/modsec_audit.log (ModSecurity)
```

---

## 5. Jobs/Queue Pattern

### 5.1 Base Job Structure
**Implements:** `ShouldQueue` interface
**Uses Traits:**
- `Dispatchable` - Enables dispatch() method
- `InteractsWithQueue` - Allows explicit job handling
- `SerializesModels` - For model serialization
- `Queueable` - Queue configuration

### 5.2 Real Example: ProcessFirewallCheckJob
**File:** `/Users/abkrim/SitesLR12/unblock/app/Jobs/ProcessFirewallCheckJob.php`

```php
class ProcessFirewallCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $ip,
        public int $userId,
        public int $hostId,
        public ?int $copyUserId = null
    ) {}

    public function handle(
        SshConnectionManager $sshManager,
        FirewallAnalyzerFactory $analyzerFactory,
        FirewallUnblocker $unblocker,
        ReportGenerator $reportGenerator,
        AuditService $auditService
    ): void {
        try {
            $user = $this->loadUser($this->userId);
            $host = $this->loadHost($this->hostId);

            DB::transaction(function () use (...$dependencies) {
                // 1. Perform firewall analysis
                $analysisResult = $this->performFirewallAnalysis($this->ip, $host, $sshManager, $analyzerFactory);

                // 2. Perform unblock if IP is blocked
                if ($analysisResult->isBlocked()) {
                    $unblockResults = $this->performUnblockOperations($this->ip, $host, $analysisResult, $unblocker);
                }

                // 3. Generate report
                $report = $reportGenerator->generateReport(...);

                // 4. Audit the operation
                $auditService->logFirewallCheck(...);

                // 5. Dispatch notification job
                SendReportNotificationJob::dispatch((string) $report->id, $this->copyUserId);
            });
        } catch (Exception $e) {
            Log::error('Firewall check job failed', [...]);
            // Audit failure
        }
    }
}
```

### 5.3 Job Dispatch Pattern
```php
// Dispatch immediately
Job::dispatch(param1: $value1, param2: $value2);

// Dispatch with delay
Job::dispatch(...)->delay(now()->addMinutes(5));

// Chain jobs
Bus::chain([
    new FirstJob(),
    new SecondJob(),
])->dispatch();
```

### 5.4 Example: RemoveExpiredBfmWhitelistIps
**File:** `/Users/abkrim/SitesLR12/unblock/app/Jobs/RemoveExpiredBfmWhitelistIps.php`

DirectAdmin BFM whitelist cleanup job.

```php
class RemoveExpiredBfmWhitelistIps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Get expired entries by host to minimize SSH connections
        $entriesByHost = BfmWhitelistEntry::expired()
            ->with('host')
            ->get()
            ->groupBy('host_id');

        foreach ($entriesByHost as $hostId => $entries) {
            $host = $entries->first()->host;

            if (! $host || $host->panel !== 'directadmin') {
                continue;
            }

            try {
                $this->removeEntriesFromHost($host, $entries);
            } catch (Exception $e) {
                Log::error('Failed to remove expired entries from host', [...]);
            }
        }
    }
}
```

---

## 6. Scheduled Commands (Routes/Console)

### 6.1 Schedule Configuration
**File:** `/Users/abkrim/SitesLR12/unblock/routes/console.php`

```php
if (config('unblock.cron_active')) {
    Schedule::command(WhmcsSynchro::class)->dailyAt('02:03');
}

// Simple Unblock: Cleanup old OTP records
Schedule::command('simple-unblock:cleanup-otp --force')->dailyAt('03:00');

// DirectAdmin BFM: Remove expired whitelist IPs (every hour)
Schedule::job(new RemoveExpiredBfmWhitelistIps)->hourly();

// Pattern Detection: Detect attack patterns (every hour)
Schedule::command('patterns:detect --force')->hourly();

// GeoIP: Update MaxMind database (weekly on Sundays at 2am)
Schedule::command('geoip:update')->weekly()->sundays()->at('02:00');
```

### 6.2 Schedule Frequency Methods
```php
->everyMinute()
->everyFiveMinutes()
->everyTenMinutes()
->everyFifteenMinutes()
->everyThirtyMinutes()
->hourly()
->hourlyAt(17)
->daily()
->dailyAt('13:00')
->twiceDaily()
->weekly()
->weeklyOn(1, '8:00')  // 1 = Monday
->monthly()
->quarterly()
->yearly()
->between('8:00', '17:00')
->unlessBetween('23:00', '4:00')
->onSuccess()
->onFailure()
```

---

## 7. Configuration Structure

### 7.1 config/unblock.php
**File:** `/Users/abkrim/SitesLR12/unblock/config/unblock.php`

Key configuration options:

```php
return [
    'special' => env('SPECIAL'),
    'admin_email' => env('ADMIN_EMAIL'),
    'send_admin_report_email' => env('SEND_ADMIN_REPORT_EMAIL', true),
    'attempts' => env('ATTEMPTS', 10),
    'report_expiration' => env('REPORT_EXPIRATION', 604800), // 10 days
    'cron_active' => env('CRON_ACTIVE', false),

    // Notification settings
    'notify_connection_failures' => env('NOTIFY_CONNECTION_FAILURES', true),
    'notify_critical_errors' => env('NOTIFY_CRITICAL_ERRORS', true),
    'critical_hosts' => explode(',', env('CRITICAL_HOSTS', '')),

    // Error retry settings
    'max_retry_attempts' => env('MAX_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('RETRY_DELAY', 5),

    // WHMCS Integration
    'whmcs' => [
        'enabled' => env('WHMCS_SYNC_ENABLED', true),
        'schedule' => env('WHMCS_SYNC_SCHEDULE', '02:03'),
        'sync' => [
            'users' => ['enabled' => true, 'create_if_not_exists' => true, ...],
            'hostings' => ['enabled' => true, 'sync_with_user' => true],
            'hosts' => ['enabled' => false],
        ],
        'panels' => ['cpanel', 'directadmin', 'da'],
        'cache' => ['users' => 600, 'hosts' => 14400],
    ],

    // HQ (Headquarters) host configuration
    'hq' => [
        'host_id' => env('HQ_HOST_ID'),
        'fqdn' => env('HQ_HOST_FQDN', ''),
        'ttl' => env('HQ_WHITELIST_TTL', 7200), // Temporary whitelist TTL in seconds
    ],

    // Simple Unblock Mode (v1.2.0+)
    'simple_mode' => [
        'enabled' => env('UNBLOCK_SIMPLE_MODE', false),
        'throttle_per_minute' => env('UNBLOCK_SIMPLE_THROTTLE_PER_MINUTE', 3),
        'throttle_email_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_EMAIL_PER_HOUR', 5),
        'throttle_domain_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_DOMAIN_PER_HOUR', 10),
        'throttle_subnet_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_SUBNET_PER_HOUR', 20),
        'throttle_global_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_GLOBAL_PER_HOUR', 500),
        'block_duration_minutes' => env('UNBLOCK_SIMPLE_BLOCK_DURATION', 15),
        'strict_match' => env('UNBLOCK_SIMPLE_STRICT_MATCH', true),
        'silent_log' => env('UNBLOCK_SIMPLE_SILENT_LOG', true),
        'otp_enabled' => env('UNBLOCK_SIMPLE_OTP_ENABLED', true),
        'otp_expires_minutes' => env('UNBLOCK_SIMPLE_OTP_EXPIRES', 5),
        'otp_length' => env('UNBLOCK_SIMPLE_OTP_LENGTH', 6),
    ],
];
```

---

## 8. Exception Handling

### 8.1 Custom Exceptions

#### FirewallException
**File:** `/Users/abkrim/SitesLR12/unblock/app/Exceptions/FirewallException.php`

Base exception for firewall operations.

```php
class FirewallException extends Exception
{
    public function __construct(
        string $message,
        ?string $hostName = null,
        ?string $ipAddress = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        // Logs to 'firewall' channel
        Log::channel('firewall')->error($this->getMessage(), $context);
    }

    public function getHostName(): ?string { /* ... */ }
    public function getIpAddress(): ?string { /* ... */ }
    public function getContext(): array { /* ... */ }
}
```

#### ConnectionFailedException
**File:** `/Users/abkrim/SitesLR12/unblock/app/Exceptions/ConnectionFailedException.php`

SSH connection failure exception with admin notifications.

```php
class ConnectionFailedException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $hostName = null,
        private readonly int $attempts = 1,
        private readonly ?string $ipAddress = null,
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->logError();
        $this->notifyAdmins(); // Notifies if attempts >= 3
    }
}
```

#### Other Exceptions
- `InvalidIpException` - Invalid IP address format
- `InvalidKeyException` - Invalid SSH key
- `CommandExecutionException` - Command execution failed
- `CsfServiceException` - CSF service error
- `AuthenticationException` - Authentication failure

---

## 9. Code Patterns and Conventions

### 9.1 PHP Conventions
- **Strict Types:** All files should start with `declare(strict_types=1);`
- **Naming:**
  - Variables: `camelCase`
  - Classes: `PascalCase`
  - Methods: `camelCase`
  - Constants: `UPPER_SNAKE_CASE`
- **Comments:** In English for team clarity
- **Commits:** In English following conventional commits

### 9.2 Service/Action Return Patterns

**Option 1: Simple Array Response**
```php
return [
    'success' => true,
    'message' => __('message.key'),
    'data' => $data, // optional
];
```

**Option 2: Void with Side Effects**
```php
public function handle(...): void
{
    // Process and return nothing
    // Side effects: logging, event dispatch, job dispatch
}
```

**Option 3: Object Return**
```php
return $model; // Model or custom DTO
```

**Option 4: Throw Exception**
```php
throw new FirewallException("Error message", previous: $e);
```

### 9.3 Logging Pattern
```php
use Illuminate\Support\Facades\Log;

Log::info('Operation started', [
    'key1' => $value1,
    'key2' => $value2,
]);

Log::warning('Warning message', ['context' => 'data']);

Log::error('Error occurred', [
    'host_id' => $host->id,
    'error' => $e->getMessage(),
]);

// Channel-specific
Log::channel('firewall')->info('Firewall operation', []);
```

### 9.4 Database Transaction Pattern
```php
DB::transaction(function () {
    // Multiple DB operations
    $model->save();
    $otherModel->update();
}, attempts: 3);
```

### 9.5 Model Loading Pattern
```php
private function loadUser(int $userId): User
{
    $user = User::find($userId);
    if (! $user) {
        throw new InvalidArgumentException("User with ID {$userId} not found");
    }
    return $user;
}
```

---

## 10. Testing Considerations

### 10.1 Test Framework
**Using:** Pest (Laravel testing framework)

### 10.2 Test Organization
- Tests mirror app structure
- Separate test classes per class being tested
- Prefer descriptive test names

### 10.3 Database Testing
```php
// Reset database state
$this->actingAs($user); // For auth tests

// Assertions
$this->assertDatabaseHas('table', ['column' => 'value']);
$this->assertDatabaseMissing('table', ['column' => 'value']);
```

---

## 11. Integration Points for Phase 2

### 11.1 Action Integration
When creating sync actions, follow these patterns:

1. **Input Validation** - Check required fields
2. **Model Loading** - Use safe loading with error handling
3. **Permission Checks** - Verify user access
4. **Job Dispatching** - Use queue for long-running tasks
5. **Logging** - Comprehensive activity logging
6. **Event Dispatching** - For decoupled features
7. **Return or Throw** - Clear result indication

### 11.2 Command Integration
When creating sync commands:

1. **Signature Definition** - Clear arguments and options
2. **Help Text** - Descriptive description
3. **Mode Selection** - Interactive vs non-interactive
4. **Action Calling** - Reuse action logic
5. **Output** - Use Laravel Prompts for better UX
6. **Error Handling** - Try-catch with user-friendly messages
7. **Exit Code** - Return SUCCESS/FAILURE consistently

### 11.3 SSH Operations
For any SSH command execution:

1. **Key Management** - Use SshConnectionManager for key handling
2. **Host Validation** - Ensure host exists and is accessible
3. **Command Building** - Use FirewallService for standard commands
4. **Panel Detection** - Use analyzer factory for panel-specific logic
5. **Error Handling** - Catch and log SSH failures
6. **Resource Cleanup** - Always cleanup SSH keys and sessions

### 11.4 Panel-Specific Implementation
For panel-specific logic:

1. **Use Factory Pattern** - FirewallAnalyzerFactory approach
2. **Implement Interface** - For consistency
3. **Panel Detection** - Check $host->panel consistently
4. **File Paths** - Know panel-specific file locations
5. **Service Compatibility** - Check what services exist on each panel

---

## 12. Key Files Reference

| File | Purpose |
|------|---------|
| `app/Services/SshConnectionManager.php` | SSH connection management |
| `app/Services/FirewallService.php` | Firewall command execution |
| `app/Services/SshSession.php` | Session-based SSH operations |
| `app/Models/Host.php` | Host model with encrypted SSH keys |
| `app/Actions/CheckFirewallAction.php` | Firewall check orchestrator |
| `app/Actions/UnblockIpAction.php` | IP unblocking action |
| `app/Actions/SimpleUnblockAction.php` | Simple mode unblocking |
| `app/Actions/WhmcsSynchro.php` | WHMCS synchronization action |
| `app/Console/Commands/UnblockIpCommand.php` | Unblock CLI command |
| `app/Console/Commands/UserCreateCommand.php` | User creation command |
| `app/Console/Commands/AddHostKeyCommand.php` | SSH key management |
| `app/Jobs/ProcessFirewallCheckJob.php` | Async firewall processing |
| `app/Jobs/RemoveExpiredBfmWhitelistIps.php` | DirectAdmin cleanup job |
| `app/Services/Firewall/FirewallAnalyzerFactory.php` | Panel analyzer factory |
| `app/Services/Firewall/CpanelFirewallAnalyzer.php` | cPanel analyzer |
| `app/Services/Firewall/DirectAdminFirewallAnalyzer.php` | DirectAdmin analyzer |
| `config/unblock.php` | Configuration |
| `routes/console.php` | Scheduled commands |

---

## 13. Next Steps for Phase 2

### Actions to Create
1. `SyncHostsFromConfigAction` - Sync hosts from config files
2. `SyncHostsFromApiAction` - Sync hosts from API endpoints
3. `ValidateHostConnectionAction` - Test host connectivity
4. `UpdateHostMetadataAction` - Update host information
5. `SyncHostStatusAction` - Check host status and availability

### Commands to Create
1. `sync:hosts-from-config` - CLI for manual sync from config
2. `sync:hosts-from-api` - CLI for manual sync from API
3. `sync:validate-connections` - Test all host connections
4. `sync:schedule` - Display/manage sync schedule

### Jobs to Create
1. `SyncHostsJob` - Async hosts sync job
2. `ValidateHostConnectionJob` - Async connection validation
3. `UpdateHostMetadataJob` - Async metadata update

---

**Document Version:** 1.0
**Last Updated:** 2025-10-28
**Author:** Code Exploration Analysis
