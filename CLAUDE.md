# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 12 firewall management system** for hosting services with WHMCS integration. The system provides secure IP unblocking, firewall analysis, and user permission management for hosting providers.

## Key Development Commands

### Testing
```bash
# Run full test suite (110 tests)
php artisan test

# Run specific test groups
php artisan test --filter=OtpAuthentication
php artisan test --filter=WhmcsSync
php artisan test --filter=UnifiedDashboard

# Run tests with coverage
php artisan test --coverage
```

### Code Quality
```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Check specific files
./vendor/bin/pint app/Models/User.php
```

### Development Server
```bash
# Start development server
php artisan serve

# Build frontend assets
pnpm run dev

# Build for production
pnpm run build
```

### Database
```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

### WHMCS Integration
```bash
# Manual WHMCS synchronization
php artisan whmcs:synchro

# Schedule runs automatically at 02:03 daily
```

### User Management
```bash
# Manage user permissions
php artisan user:authorize

# Create admin user
php artisan user:authorize --admin

# Unblock IP addresses
php artisan unblock:ip {ip_address} --host={host_id}
```

## Architecture Overview

### Core Components

1. **Authentication System**: 
   - Uses Spatie Laravel One-Time Passwords
   - 6-digit OTP codes sent via email
   - 5-minute expiration with rate limiting

2. **Permission System**:
   - **Principal Users**: Own hosting services and VPS
   - **Authorized Users**: Delegated access to specific resources
   - **Admin Users**: Full system access via Filament
   - Hybrid permission model combining direct host access and hosting-specific permissions

3. **Firewall Management**:
   - SSH-based firewall operations using Spatie SSH
   - Support for CSF, DirectAdmin BFM, and ModSecurity
   - Automated IP unblocking with fallback mechanisms

4. **WHMCS Integration**:
   - Automatic synchronization of users, hostings, and hosts
   - Configurable sync schedules and rules
   - Soft delete support for graceful data management

### Key Models

- **User**: Central user model with OTP authentication, permission system, and WHMCS integration
- **Host**: Server definitions with SSH connection details and panel types
- **Hosting**: Individual hosting accounts linked to users and hosts
- **UserHostingPermission**: Granular permission system for hosting access
- **Report**: Firewall analysis reports with email notifications

### Services Architecture

- **FirewallService**: Direct SSH execution for firewall operations
- **SshConnectionManager**: Manages SSH connections and key handling
- **ReportGenerator**: Creates detailed firewall analysis reports
- **AuditService**: Activity logging and security audit trails

## Technology Stack

- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Livewire 3 + Alpine.js + Tailwind CSS
- **Admin Panel**: Filament v3
- **Database**: SQLite (development) / MySQL (production)
- **Testing**: PEST 3 framework
- **Package Manager**: pnpm
- **Code Style**: Laravel Pint with custom rules

## Development Guidelines

### Code Standards
- All code must be in English (variables, methods, comments)
- User-facing text in Spanish using `__()` translation helpers
- Follow Laravel coding standards and PSR-12
- Use strict typing: `declare(strict_types=1);`

### Testing Requirements
- All new features must include tests
- Maintain 100% test success rate
- Use PEST for new tests
- Test both happy path and error scenarios

#### Testing Configuration Rule
**Tests must not rely on values defined in `.env.testing` for specific cases.** Instead, each test should explicitly configure the required parameters using the `config()` helper within its own method.

**Rationale & Best Practices:**
- Ensures each test is self-contained and deterministic, without hidden configuration dependencies
- Improves readability and maintainability by keeping all relevant settings visible in the test itself
- Prevents "side effects" from sharing a common environment file across multiple tests

**Example:**
```php
test('session timeout works correctly', function () {
    // ✅ Good: Explicit configuration within test
    config()->set('session.lifetime', 240); // 4 hours
    
    // Test implementation...
    expect(config('session.lifetime'))->toBe(240);
});
```

**Avoid:**
```php
test('session timeout works correctly', function () {
    // ❌ Bad: Relying on .env.testing values
    expect(config('session.lifetime'))->toBe(240); // Hidden dependency
});
```

### Security Considerations
- Never log or expose SSH keys or passwords
- Use proper input validation for IP addresses
- Implement rate limiting for authentication attempts
- Use Spatie Activity Log for audit trails

### SSH Key Management
- Keys are stored in storage/app/temp/ with proper permissions
- Use FirewallService::generateSshKey() for key normalization
- Clean up temporary SSH keys after use
- Validate SSH connections before executing commands

## Common Development Patterns

### Action Pattern
Use Lorisleiva Actions for business logic:
```php
class UnblockIpAction
{
    use AsAction;
    
    public function handle(string $ip, int $hostId, string $keyName): array
    {
        // Implementation
    }
}
```

### Permission Checking
Use the User model's built-in permission methods:
```php
$user->hasAccessToHost($hostId)
$user->hasAccessToHosting($hostingId)
```

### SSH Operations
Use FirewallService for all SSH operations:
```php
$this->firewallService->checkProblems($host, $keyPath, 'unblock', $ip);
```

## Configuration

### Environment Variables
Key configuration in `.env`:
- `WHMCS_SYNC_ENABLED`: Enable WHMCS integration
- `ADMIN_EMAIL`: Admin notifications recipient
- `CRITICAL_HOSTS`: Comma-separated list of critical host IDs
- `MAX_RETRY_ATTEMPTS`: SSH retry attempts (default: 3)

### Config Files
- `config/unblock.php`: Main application configuration
- `config/filesystems.php`: SSH key storage configuration
- `pint.json`: Code formatting rules

## Debugging & Monitoring

### Logging
- Application logs: `storage/logs/laravel.log`
- Firewall logs: `storage/logs/firewall-{date}.log`
- SSH debug logs: `storage/logs/ssh_debug.log`

### Debug Tools
- Laravel Debugbar (development only)
- Spatie Ray for debugging
- Built-in error tracking and notifications

## Production Considerations

### Performance
- Use Redis for caching and queues
- Configure proper database indexes
- Implement supervisor for queue workers
- Use proper SSH key management

### Security
- Implement proper firewall rules
- Use secure SSH key storage
- Enable rate limiting
- Configure proper logging levels

### Monitoring
- Set up queue monitoring
- Configure email notifications for critical errors
- Monitor SSH connection failures
- Track user activity and permissions


## Importar Preferencias Globales
@~/.claude/laravel-php-guidelines.md
