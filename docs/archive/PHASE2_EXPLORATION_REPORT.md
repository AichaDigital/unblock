# Phase 2: Sync System with Actions and Commands - Codebase Exploration Report

**Date:** October 28, 2025  
**Status:** Complete Investigation Report  
**Target:** Infrastructure for Phase 2 Implementation

---

## 1. SSH CONNECTION INFRASTRUCTURE

### 1.1 SshConnectionManager Service
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Services/SshConnectionManager.php`

**Key Responsibilities:**
- Generate temporary SSH keys from Host model's encrypted `hash` field
- Prepare SSH multiplexing directory (`/tmp/cm`)
- Execute SSH commands on remote hosts
- Cleanup temporary SSH keys
- Create SSH session objects for managing multiple commands

**Key Methods:**

```php
// Generate temporary SSH key
public function generateSshKey(string $hash): string
  - Normalizes line endings (handle CRLF, CR, LF)
  - Stores key in `storage/app/.ssh/` directory
  - Returns full path to key file

// Prepare multiplexing
public function prepareMultiplexingPath(): void
  - Creates /tmp/cm directory with 0755 permissions
  - Throws ConnectionFailedException if fails

// Execute command
public function executeCommand(Host $host, string $sshKeyPath, string $command): string
  - Uses Spatie\Ssh library
  - Connects as root user to host->fqdn on host->port_ssh (default 22)
  - Configures SSH multiplexing: ControlMaster=auto, ControlPath=$controlPath, ControlPersist=60s
  - Returns trimmed output
  - Logs warnings for non-zero exit codes

// Create session
public function createSession(Host $host): SshSession
  - Returns SshSession object for managing multiple commands
  - SshSession auto-cleanup on destruction

// Cleanup key
public function removeSshKey(string $keyPath): void
  - Removes key file using Storage disk
```

**SSH Key Management:**
- Location: Host model `hash` field (encrypted with Laravel Crypt)
- Private key format: OpenSSH or RSA
- Normalization: Ensures Unix line endings + final newline
- Temporary storage: `storage/app/.ssh/key_RANDOM.pem`
- Permissions: Private disk (0600)

### 1.2 FirewallService
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Services/FirewallService.php`

**Responsibilities:**
- Check firewall problems via SSH
- Build panel-specific commands
- Process ModSecurity JSON output
- Manage SSH keys

**Key Command Patterns:**
```php
// CSF Firewall
'csf' => "csf -g {$ip}"
'csf_deny_check' => "cat /etc/csf/csf.deny | grep {$ip} || true"
'csf_tempip_check' => "cat /var/lib/csf/csf.tempip | grep {$ip} || true"

// ModSecurity (DirectAdmin)
'mod_security_da' => "cat /var/log/nginx/modsec_audit.log | grep {$ip} || true"

// Mail Services
'exim_directadmin' => "cat /var/log/exim/mainlog | grep -Ea {$ip} | grep 'authenticator failed'"
'dovecot_directadmin' => "cat /var/log/mail.log | grep -Ea {$ip} | grep 'auth failed'"

// DirectAdmin BFM (Brute Force Manager)
'da_bfm_check' => "cat /usr/local/directadmin/data/admin/ip_blacklist | grep -E '^{$ip}(\\s|\$)' || true"
'da_bfm_remove' => "sed -i '/^{$ip}(\\s|\$)/d' /usr/local/directadmin/data/admin/ip_blacklist"
'da_bfm_whitelist_add' => "echo '{$ip}' >> /usr/local/directadmin/data/admin/ip_whitelist"

// Unblock & Whitelist
'unblock' => "csf -dr {$ip} && csf -tr {$ip} && csf -ta {$ip} 86400"
'whitelist' => "csf -ta {$ip} 86400"
'whitelist_7200' => "csf -ta {$ip} " . max(60, (int)(config('unblock.hq.ttl') ?? 7200))
```

### 1.3 SshSession
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Services/SshSession.php`

**Responsibilities:**
- Manages single SSH session with host
- Executes commands with comprehensive logging
- Auto-cleanup on destruction

**Usage Pattern:**
```php
$session = $sshManager->createSession($host);
try {
    $output = $session->execute($command);
} finally {
    $session->cleanup();
}
```

---

## 2. EXISTING ACTIONS PATTERN

### 2.1 Base Action Pattern
**Location:** `app/Actions/`

**Trait:** `Lorisleiva\Actions\Concerns\AsAction`
- Converts class into executable action
- Supports `handle()` method as main entry point
- Can be invoked as: `ActionClass::dispatch()` or `app(ActionClass::class)->handle(...)`

### 2.2 UnblockIpAction
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Actions/UnblockIpAction.php`

**Pattern & Structure:**
```php
class UnblockIpAction {
    use AsAction;
    
    public function __construct(
        protected FirewallService $firewallService
    ) {}
    
    public function handle(string $ip, int $hostId, string $keyName): array
    {
        // 1. Load and validate models
        // 2. Execute firewall operations
        // 3. Handle panel-specific logic (DirectAdmin BFM)
        // 4. Return status array
        
        return [
            'success' => bool,
            'message' => string,
            'error?' => string
        ];
    }
}
```

**Characteristics:**
- Constructor injection of dependencies
- Returns structured array with success/message/error
- Handles exceptions with try-catch
- Logs warnings for non-fatal errors
- Panel-specific logic (DirectAdmin BFM removal)

### 2.3 SimpleUnblockAction
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Actions/SimpleUnblockAction.php`

**Pattern & Structure:**
```php
class SimpleUnblockAction {
    use AsAction;
    
    public function handle(string $ip, string $domain, string $email): void
    {
        // 1. Normalize domain
        // 2. Check rate limits (multi-vector)
        // 3. Find which host has IP blocked
        // 4. Dispatch ProcessSimpleUnblockJob
        // 5. Log activity with activity() facade
        // 6. Dispatch events
    }
    
    private function checkEmailRateLimit(string $email): void
    private function checkDomainRateLimit(string $domain): void
    private function normalizeDomain(string $domain): string
    private function findHostWithBlockedIp(string $ip, $hosts): ?Host
    private function notifyAdminSilentAttempt(...): void
}
```

**Characteristics:**
- Async job dispatch for actual work
- Multi-vector rate limiting (email, domain, subnet, global)
- Rate limiter keys: `simple_unblock:{vector}:{identifier}`
- Activity logging with properties (GDPR compliant - email hashed)
- Event dispatch for reputation tracking

### 2.4 CheckFirewallAction
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Actions/CheckFirewallAction.php`

**Pattern & Structure:**
```php
class CheckFirewallAction {
    use AsAction;
    
    public function handle(string $ip, int $userId, int $hostId, ?int $copyUserId = null): array
    {
        // 1. Load and validate models
        // 2. Validate IP address
        // 3. Check user access permissions
        // 4. Dispatch ProcessFirewallCheckJob
        // 5. Dispatch ProcessHqWhitelistJob (in parallel)
        // 6. Return success/error response
    }
    
    private function validateUserAccess(User $user, Host $host): void
    private function loadUser(int $userId): User
    private function loadHost(int $hostId): Host
}
```

**Characteristics:**
- Returns immediately to user (async processing)
- Validates user access permissions
- Dispatches multiple jobs in parallel
- Checks development mode flag

### 2.5 Common Action Patterns

**Dependency Injection:**
- Services injected via constructor
- Resolved from container in handle()

**Error Handling:**
- Specific exceptions thrown (InvalidIpException, FirewallException, etc.)
- Detailed logging with context

**Return Values:**
- Structured arrays: `['success' => bool, 'message' => string, 'error' => string]`
- Or void with side effects (dispatch jobs/events)

---

## 3. EXISTING COMMANDS

### 3.1 UnblockIpCommand
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/UnblockIpCommand.php`

**Signature & Structure:**
```php
protected $signature = 'unblock:ip
    {ip : IP address to unblock}
    {--host= : Specific host ID to unblock from}';

public function handle(): int
{
    // 1. Parse arguments/options
    // 2. Validate required options
    // 3. Call action (UnblockIpAction)
    // 4. Handle result & return exit code
}
```

**Characteristics:**
- Arguments in `{}` (required)
- Options in `--` (optional)
- Returns `Command::SUCCESS` or `Command::FAILURE`
- Uses info(), error(), line() for output

### 3.2 UserCreateCommand
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/UserCreateCommand.php`

**Signature & Pattern:**
```php
protected $signature = 'user:create
    {--no-secure : Disable complex password requirements}
    {--admin : Create an admin user}
    {--email= : Email address}
    {--first-name= : First name}
    {--last-name= : Last name}
    {--company-name= : Company name}
    {--password= : Password}';

public function handle(): int
{
    // 1. Check if non-interactive (all options provided)
    // 2. Route to handleNonInteractive() or handleInteractive()
    // 3. Validate inputs
    // 4. Create user in transaction
    // 5. Return result
}
```

**Characteristics:**
- Supports both interactive and non-interactive modes
- Uses Laravel Prompts for interactive input (text, password, confirm)
- DB::transaction for data consistency
- Detailed validation with custom messages
- Password validation with Password rules

### 3.3 DetectPatternsCommand
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/DetectPatternsCommand.php`

**Signature & Pattern:**
```php
protected $signature = 'patterns:detect
    {--type= : Specific pattern type}
    {--force : Skip confirmation}';

public function handle(): int
{
    // 1. Initialize detectors
    // 2. Run detection algorithms
    // 3. Format and display results in table
    // 4. Return success
}
```

**Characteristics:**
- Instantiates service classes
- Uses table() for formatted output
- Collects results from multiple sources
- Returns count and details

### 3.4 TestHostConnectionCommand
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/Develop/TestHostConnectionCommand.php`

**Signature & Pattern:**
```php
protected $signature = 'develop:test-host-connection {--host-id= : ID específico}';

public function handle(): int
{
    // 1. Select host (interactive or via --host-id)
    // 2. Display host info
    // 3. Diagnose SSH key
    // 4. Test SSH connection
    // 5. Show debug info
}
```

**Methods:**
- `selectHost()` - Load host by ID or interactive selection
- `diagnoseKey()` - Validate SSH key format, type, permissions
- `testConnection()` - Execute SSH command (whoami)
- `showDebugInfo()` - Display debugging information

**Characteristics:**
- Uses Laravel Prompts functions (select, table, info, error, warning)
- Key normalization: Unix line endings + final newline
- File permissions: 0600 for SSH keys
- Uses `exec()` for shell commands (ssh-keygen, openssl, ssh)

### 3.5 Common Command Patterns

**Structure:**
```php
protected $signature = 'namespace:command {arg : description} {--option= : description}';
protected $description = 'Command description';

public function handle(): int
{
    // Implementation
    return Command::SUCCESS; // or Command::FAILURE
}
```

**Output Methods:**
- `info()`, `warn()`, `error()`, `line()` - Output messages
- `table()` - Display formatted table
- `ask()`, `confirm()`, `choice()` - Interactive input
- `newLine()` - Blank line

**Integration:**
- Commands can call Actions
- Commands can use database operations
- Commands can dispatch jobs

---

## 4. PANEL-SPECIFIC LOGIC

### 4.1 Panel Types
**Supported Panels:**
- `cpanel` - cPanel hosting control panel
- `directadmin` or `da` - DirectAdmin control panel

**Host Model Field:**
```php
protected $fillable = [
    'panel', // 'cpanel' or 'directadmin'
    // ... other fields
];
```

### 4.2 CpanelFirewallAnalyzer
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/CpanelFirewallAnalyzer.php`

**Service Checks:**
```php
'csf' => true,
'csf_specials' => true,
'exim_cpanel' => true,
'dovecot_cpanel' => true,
```

**Analysis Flow:**
1. Check CSF (ConfigServer Firewall) - primary block check
2. If blocked, check CSF specials
3. If blocked, check Exim (mail server) - CONTEXT ONLY (not blocking)
4. If blocked, check Dovecot (IMAP) - CONTEXT ONLY (not blocking)
5. If blocked, unblock and add to whitelist

**Key Logic:**
```php
// CSF provides actual blocking status
$isBlocked = str_contains($output, 'csf.deny:') || 
             str_contains($output, 'DROP') || 
             str_contains($output, 'DENYIN') ||
             str_contains($output, 'Temporary Blocks') ||
             str_contains($output, 'DENY');

// Exim and Dovecot are CONTEXT ONLY
private function analyzeEximOutput(string $output): FirewallAnalysisResult
{
    return new FirewallAnalysisResult(false, ['exim' => $output]);
}
```

### 4.3 DirectAdminFirewallAnalyzer
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/DirectAdminFirewallAnalyzer.php`

**Service Checks:**
```php
'csf' => true,
'csf_deny_check' => true,
'csf_tempip_check' => true,
'exim_directadmin' => true,
'dovecot_directadmin' => true,
'mod_security_da' => true,
'da_bfm_check' => true,
```

**Analysis Flow (7 Steps):**
1. **CSF Primary Check** - `csf -g IP` command
2. **CSF Deep Analysis** - If not blocked:
   - Check `/etc/csf/csf.deny` (permanent blocks)
   - Check `/var/lib/csf/csf.tempip` (temporary blocks)
3. **DirectAdmin BFM Check** - ALWAYS check `/usr/local/directadmin/data/admin/ip_blacklist`
4. **Service Logs** - Exim, Dovecot, ModSecurity for CONTEXT ONLY
5. **Block Actions** - Only for CSF-related blocks (not ModSecurity)
6. **Database Tracking** - For BFM whitelist entries
7. **Result Aggregation** - Combine all logs with complete structure

**BFM (Brute Force Manager) Handling:**
```php
private function checkAndRemoveFromBfmBlacklist(string $ip, string $sshKeyName): ?FirewallAnalysisResult
{
    // 1. Check if IP in /usr/local/directadmin/data/admin/ip_blacklist
    // 2. If found:
    //    a. Remove from blacklist (da_bfm_remove)
    //    b. Add to whitelist (da_bfm_whitelist_add)
    //    c. Track in BfmWhitelistEntry DB table
    //    d. Set TTL expiration (config('unblock.hq.ttl') ?? 7200)
    // 3. Return FirewallAnalysisResult
}
```

**BFM Exact IP Matching:**
```php
// Filter to ensure exact IP matches only
$parts = preg_split('/\s+/', $line);
$lineIp = $parts[0] ?? '';
if ($lineIp === $targetIp) {
    $validLines[] = $line;
}
```

### 4.4 FirewallAnalyzerFactory
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/FirewallAnalyzerFactory.php`

**Pattern:**
```php
public function createForHost(Host $host): FirewallAnalyzerInterface
{
    if (!isset($this->analyzers[$host->panel])) {
        throw new InvalidArgumentException("No analyzer for panel: {$host->panel}");
    }
    
    $analyzerClass = $this->analyzers[$host->panel];
    return new $analyzerClass($this->firewallService, $host);
}
```

**Registered Analyzers:**
```php
'directadmin' => DirectAdminFirewallAnalyzer::class,
'cpanel' => CpanelFirewallAnalyzer::class,
```

### 4.5 Panel-Specific Command Examples in SimpleUnblockJob

```php
private function buildDomainSearchCommands(string $ip, string $domain, string $panelType): array
{
    $commands = [
        // Apache/Nginx/Exim - Common to all panels
        "find /var/log/apache2 -name 'access.log*' -mtime -7 -type f -exec grep -l {$ip} {} \; 2>/dev/null | xargs grep -i {$domain} 2>/dev/null | head -1",
        "find /var/log/nginx -name 'access.log*' -mtime -7 -type f -exec grep -l {$ip} {} \; 2>/dev/null | xargs grep -i {$domain} 2>/dev/null | head -1",
        "find /var/log/exim -name 'mainlog*' -mtime -7 -type f -exec grep -l {$ip} {} \; 2>/dev/null | xargs grep -i {$domain} 2>/dev/null | head -1",
    ];
    
    if ($panelType === 'cpanel') {
        // cPanel-specific: domlogs
        $commands[] = "find /usr/local/apache/domlogs -name '*{$domain}*' -mtime -7 -type f -exec grep {$ip} {} \; 2>/dev/null | head -1";
    }
    
    return $commands;
}
```

---

## 5. JOBS PATTERN

### 5.1 ProcessFirewallCheckJob
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Jobs/ProcessFirewallCheckJob.php`

**Structure:**
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
        // 1. Perform firewall analysis
        // 2. Perform unblock operations if blocked
        // 3. Generate report
        // 4. Audit the operation
        // 5. Dispatch notification job
    }
}
```

**Usage Pattern:**
```php
ProcessFirewallCheckJob::dispatch(
    ip: $ip,
    userId: $userId,
    hostId: $hostId,
    copyUserId: $copyUserId
);
```

### 5.2 ProcessSimpleUnblockJob
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Jobs/ProcessSimpleUnblockJob.php`

**Structure:**
```php
class ProcessSimpleUnblockJob implements ShouldQueue
{
    public function __construct(
        public string $ip,
        public string $domain,
        public string $email,
        public int $hostId
    ) {}
    
    public function handle(
        SshConnectionManager $sshManager,
        FirewallAnalyzerFactory $analyzerFactory,
        FirewallUnblocker $unblocker
    ): void {
        // 1. Check if already processed (lock)
        // 2. Analyze firewall
        // 3. Check domain in server logs
        // 4. Evaluate match (full, partial, none)
        // 5. Handle full match: unblock + notify
        // 6. Handle partial/no match: admin notification only
    }
}
```

**Match Evaluation:**
```php
if ($ipIsBlocked && $domainExistsOnHost) {
    // FULL MATCH: Unblock + notify user + admin
    handleFullMatch(...);
    Cache::put($lockKey, true, now()->addMinutes(10));
} elseif ($ipIsBlocked || $domainExistsOnHost) {
    // PARTIAL MATCH: Silent admin notification
    logSilentAttempt(..., 'ip_blocked_but_domain_not_found' or 'domain_found_but_ip_not_blocked');
} else {
    // NO MATCH: Admin notification
    logSilentAttempt(..., 'no_match_found');
}
```

**Common Job Patterns:**
- Implement `ShouldQueue` interface
- Use `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels` traits
- Inject dependencies via `handle()` method
- Named constructor arguments for clarity
- Load models inside handle() method (safe for queue)
- Use DB::transaction() for consistency
- Comprehensive logging at each step

---

## 6. CONFIG STRUCTURE

### 6.1 config/unblock.php
**Location:** `/Users/abkrim/SitesLR12/unblock/config/unblock.php`

**Structure:**
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
    
    // Critical hosts for immediate notification
    'critical_hosts' => explode(',', env('CRITICAL_HOSTS', '')),
    
    // Error retry settings
    'max_retry_attempts' => env('MAX_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('RETRY_DELAY', 5),
    
    // WHMCS Integration
    'whmcs' => [
        'enabled' => env('WHMCS_SYNC_ENABLED', true),
        'schedule' => env('WHMCS_SYNC_SCHEDULE', '02:03'),
        'sync' => [
            'users' => [
                'enabled' => true,
                'create_if_not_exists' => true,
                'update_only_status' => true,
                'preserve_internal_users' => true,
            ],
            'hostings' => [
                'enabled' => true,
                'sync_with_user' => true,
            ],
            'hosts' => [
                'enabled' => false,
            ],
        ],
        'panels' => ['cpanel', 'directadmin', 'da'],
        'cache' => [
            'users' => 600,      // 10 minutes
            'hosts' => 14400,    // 4 hours
        ],
    ],
    
    // HQ host configuration
    'hq' => [
        'host_id' => env('HQ_HOST_ID'),
        'fqdn' => env('HQ_HOST_FQDN', ''),
        'ttl' => env('HQ_WHITELIST_TTL', 7200),
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

### 6.2 routes/console.php
**Location:** `/Users/abkrim/SitesLR12/unblock/routes/console.php`

**Schedule Configuration:**
```php
if (config('unblock.cron_active')) {
    Schedule::command(WhmcsSynchro::class)->dailyAt('02:03');
}

// Simple Unblock: Cleanup old OTP records
Schedule::command('simple-unblock:cleanup-otp --force')->dailyAt('03:00');

// DirectAdmin BFM: Remove expired whitelist IPs
Schedule::job(new RemoveExpiredBfmWhitelistIps)->hourly();

// Pattern Detection: Detect attack patterns
Schedule::command('patterns:detect --force')->hourly();

// GeoIP: Update MaxMind database
Schedule::command('geoip:update')->weekly()->sundays()->at('02:00');
```

---

## 7. HOST MODEL STRUCTURE

### 7.1 Host Model
**Location:** `/Users/abkrim/SitesLR12/unblock/app/Models/Host.php`

**Fillable Fields:**
```php
protected $fillable = [
    'whmcs_id',
    'fqdn',
    'alias',
    'ip',
    'port_ssh',
    'hash',              // Encrypted private SSH key
    'hash_public',       // Encrypted public SSH key
    'panel',             // 'cpanel' or 'directadmin'
    'admin',
    'whmcs_server_id',
    'hosting_manual',
];

protected $hidden = [
    'hash',              // Hidden from serialization
    'hash_public',       // Hidden from serialization
];
```

**SSH Key Encryption:**
```php
public function setHashAttribute($value): void
{
    if (!is_null($value) && $value !== '') {
        $this->attributes['hash'] = Crypt::encrypt($value);
    }
}

public function getHashAttribute($value): string
{
    if (!$value) {
        return '';
    }
    
    try {
        return Crypt::decrypt($value);
    } catch (Throwable $exception) {
        // Fallback: value might be plaintext (legacy)
        $trimmed = trim((string) $value);
        if (str_contains($trimmed, 'BEGIN OPENSSH PRIVATE KEY')) {
            return $trimmed;
        }
        return '';
    }
}
```

**Relationships:**
```php
public function hostings(): HasMany
public function users(): HasMany
```

---

## 8. KEY PATTERNS FOR PHASE 2

### 8.1 Action Pattern Summary

**Structure Template:**
```php
<?php
declare(strict_types=1);

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class MyAction
{
    use AsAction;
    
    public function __construct(
        protected DependencyService $service
    ) {}
    
    public function handle(string $param): array|void
    {
        // Validation
        // Business logic
        // Side effects (jobs, events, logging)
        
        return [
            'success' => bool,
            'message' => string,
            'data' => mixed
        ];
    }
    
    private function helperMethod(): void
    {
        // Helper logic
    }
}
```

### 8.2 Command Pattern Summary

**Structure Template:**
```php
<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MyCommand extends Command
{
    protected $signature = 'namespace:command 
                           {argument : Description}
                           {--option= : Description}';
    
    protected $description = 'Command description';
    
    public function handle(): int
    {
        try {
            // 1. Parse arguments/options
            $arg = $this->argument('argument');
            $opt = $this->option('option');
            
            // 2. Validate
            if (!$this->validate($arg)) {
                $this->error('Validation failed');
                return Command::FAILURE;
            }
            
            // 3. Execute action or logic
            $result = app(SomeAction::class)->handle($arg);
            
            // 4. Output result
            $this->info('Success: ' . $result['message']);
            
            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function validate(string $arg): bool
    {
        return !empty($arg);
    }
}
```

### 8.3 Job Pattern Summary

**Structure Template:**
```php
<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public string $param1,
        public int $param2
    ) {}
    
    public function handle(
        DependencyService $service
    ): void {
        try {
            // 1. Load models (safe in queue context)
            $model = Model::find($this->param2);
            
            // 2. Execute logic with transaction
            DB::transaction(function () use ($model, $service) {
                $result = $service->process($model);
                // Update models, dispatch events, etc.
            });
            
        } catch (Exception $e) {
            Log::error('Job failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

---

## 9. RATE LIMITING PATTERNS

**Implementation:**
```php
use Illuminate\Support\Facades\RateLimiter;

// Check rate limit
if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
    $seconds = RateLimiter::availableIn($key);
    throw new RuntimeException(__('messages.rate_limit_exceeded', ['seconds' => $seconds]));
}

// Record attempt
RateLimiter::hit($key, 3600); // 1 hour TTL
```

**Keys Pattern:**
```php
// Email vector
$key = "simple_unblock:email:{$emailHash}";

// Domain vector
$key = "simple_unblock:domain:{$domain}";

// Subnet vector
$key = "simple_unblock:subnet:{$subnet}";

// Global vector
$key = "simple_unblock:global";
```

---

## 10. EXCEPTION HANDLING

**Custom Exceptions (app/Exceptions/):**
- `ConnectionFailedException` - SSH connection failures
- `InvalidIpException` - Invalid IP format
- `InvalidKeyException` - Invalid SSH key
- `FirewallException` - General firewall operation failures

**Exception Raising:**
```php
throw new ConnectionFailedException(
    message: $msg,
    host: $host->fqdn,
    port: $port,
    previous: $e
);

throw new InvalidIpException(
    ip: $ip,
    message: "Invalid IP: {$ip}"
);
```

---

## 11. LOGGING PATTERNS

**Structured Logging:**
```php
Log::info('Operation completed', [
    'ip_address' => $ip,
    'host_id' => $host->id,
    'host_fqdn' => $host->fqdn,
    'result' => $result,
    'execution_time_ms' => $executionTime,
]);

Log::warning('Non-fatal issue', [
    'ip' => $ip,
    'host' => $host->fqdn,
    'error' => $error,
]);

Log::error('Operation failed', [
    'error_message' => $e->getMessage(),
    'exception_class' => get_class($e),
    'trace' => $e->getTraceAsString(),
]);
```

**Activity Logging (Spatie Activity Log):**
```php
activity()
    ->withProperties([
        'ip' => $ip,
        'domain' => $domain,
        'email_hash' => hash('sha256', $email),
        'result' => 'success',
    ])
    ->log('simple_unblock_request');
```

---

## 12. READY FOR PHASE 2 IMPLEMENTATION

Based on this exploration, you have:

1. **SSH Infrastructure**: SshConnectionManager + SshSession for secure remote command execution
2. **Action Pattern**: Use `AsAction` trait with dependency injection
3. **Command Pattern**: Extend Command class with signature/description
4. **Job Pattern**: Implement ShouldQueue with dependency injection in handle()
5. **Panel Detection**: Use FirewallAnalyzerFactory to route to correct analyzer
6. **Rate Limiting**: Use RateLimiter facade with structured keys
7. **Error Handling**: Use custom exceptions with context
8. **Logging**: Use Log facade with structured arrays + activity() for auditing

**Key Files to Study:**
- `/Users/abkrim/SitesLR12/unblock/app/Actions/SimpleUnblockAction.php` - Best Action pattern example
- `/Users/abkrim/SitesLR12/unblock/app/Jobs/ProcessSimpleUnblockJob.php` - Best Job pattern example
- `/Users/abkrim/SitesLR12/unblock/app/Console/Commands/UserCreateCommand.php` - Best Command pattern example
- `/Users/abkrim/SitesLR12/unblock/app/Services/SshConnectionManager.php` - SSH infrastructure
- `/Users/abkrim/SitesLR12/unblock/app/Services/Firewall/DirectAdminFirewallAnalyzer.php` - Complex logic example

---

