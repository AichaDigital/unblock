# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2025-10-21

### Added - Simple Unblock Mode
- **NEW FEATURE**: Anonymous IP unblocking without authentication
- Public-facing form for simplified unblocking workflow
- Cross-validation system: IP must be blocked AND domain must exist in server logs
- Anonymous user system for maintaining database referential integrity
- Aggressive rate limiting (3 requests/minute by IP)
- Comprehensive activity logging for all attempts (success and failures)
- Email notifications for users and administrators
- Domain normalization (lowercase, www removal)
- Support for both cPanel and DirectAdmin panels
- Cache locking to prevent duplicate processing
- Search in Apache, Nginx, and Exim logs
- Complete test coverage (46 tests, 100% passing)

### Technical Implementation
- **New Components**: 
  - `SimpleUnblockAction`: Domain validation and job dispatching
  - `ProcessSimpleUnblockJob`: Core processing with cross-validation
  - `SendSimpleUnblockNotificationJob`: Async email notifications
  - `SimpleUnblockForm`: Livewire component with IP autodetection
  - `ThrottleSimpleUnblock`: IP-based rate limiting middleware
  - `AnonymousUserService`: Singleton for anonymous user management
  
- **Configuration**:
  - Conditional route registration (enable/disable via config)
  - Configurable throttling (requests per minute)
  - Configurable strict match mode
  - Silent logging option for non-matches

- **Security**:
  - Shell argument escaping for all user input
  - Domain regex validation
  - Generic failure responses (prevents enumeration)
  - Email hashing in activity logs
  - Rate limiting per IP address

### Files Added (18)
- `app/Actions/SimpleUnblockAction.php`
- `app/Http/Middleware/ThrottleSimpleUnblock.php`
- `app/Jobs/ProcessSimpleUnblockJob.php`
- `app/Jobs/SendSimpleUnblockNotificationJob.php`
- `app/Livewire/SimpleUnblockForm.php`
- `app/Mail/SimpleUnblockNotificationMail.php`
- `app/Services/AnonymousUserService.php`
- `database/seeders/AnonymousUserSeeder.php`
- `resources/views/livewire/simple-unblock-form.blade.php`
- `resources/views/emails/simple-unblock-success.blade.php`
- `resources/views/emails/simple-unblock-admin-alert.blade.php`
- `lang/en/simple_unblock.php`
- `lang/es/simple_unblock.php`
- `docs/SIMPLE_UNBLOCK_FEATURE.md` (comprehensive documentation)
- 5 test files with 46 tests

### Files Modified (7)
- `.env.example`: Added Simple Mode configuration variables
- `bootstrap/app.php`: Registered throttle.simple.unblock middleware
- `config/unblock.php`: Added simple_mode configuration section
- `routes/web.php`: Conditional route registration
- `database/factories/ReportFactory.php`: Added anonymous() method
- `database/seeders/DatabaseSeeder.php`: Updated for anonymous user
- `README.md`: Added Simple Unblock Mode documentation

### Testing
- **303 tests passing** (1057 assertions)
- **0 tests skipped**
- **PHPStan**: Level max, 0 errors
- **Laravel Pint**: All files formatted
- Complete coverage for Simple Unblock feature

### Documentation
- Comprehensive technical documentation (1359 lines)
- Architecture diagrams and flow charts
- Security considerations and mitigation strategies
- Deployment checklist and requirements
- Configuration examples
- Translation coverage (English/Spanish)

### Deployment Requirements
1. Run: `php artisan db:seed --class=AnonymousUserSeeder`
2. Set: `UNBLOCK_SIMPLE_MODE=true` in `.env`
3. Configure throttling: `UNBLOCK_SIMPLE_THROTTLE_PER_MINUTE=3`
4. Verify SSH access to server log directories

### Major Cleanup and Architecture Improvements (2025-06-02)

#### 🚀 System Migration and Modernization
- **BREAKING**: Migrated from custom magic links to Spatie Laravel One-Time Passwords
- **BREAKING**: Eliminated custom rate limiting system in favor of Spatie OTP integrated solution
- **BREAKING**: Removed Laravel Breeze components and simplified authentication flow
- **BREAKING**: Dropped obsolete magic links system and related dependencies

#### ✅ Code Cleanup and Optimization
**Removed 37 obsolete files:**
- Models: `IpFailure.php`, `LoginForm.php` (2 files)
- Config files: `ban.php`, `throttle.php`, `horizon.php`, `wireui.php` (4 files)
- Auth views: `auth/otp-login.blade.php` + 7 Breeze auth pages (8 files)
- Profile views: 3 profile management pages + `profile.blade.php` (4 files)
- Breeze components: 13 unused UI components (recreated 4 essential ones)
- View components: `AppLayout.php`, `GuestLayout.php` (2 files)
- Dashboard views: `dashboard.blade.php` (1 file)
- Navigation: `welcome/navigation.blade.php` (1 file)

**Database cleanup:**
- Executed safe migrations to drop `magic_links` and `ip_failures` tables
- Maintained backward compatibility with rollback support

#### 🎯 Authentication System Improvements
- **Simplified UX**: 6-digit OTP codes instead of complex magic link URLs
- **Enhanced Security**: IP tracking, User-Agent validation, 5-minute expiration
- **Rate Limiting**: Spatie OTP integrated rate limiting (8 attempts/60 seconds)
- **Audit Trail**: Complete activity logging with Spatie Activity Log integration

#### 🔧 Architecture Refinements
- **Commands marked as deprecated**: `AddEditUserCommand`, `DeleteUserCommand`, `UserEditionCommand`
- **Updated UnblockIpCommand**: Proper integration with `UnblockIpAction`
- **Simplified routing**: OTP system as primary authentication method
- **Created minimal UI components**: Essential navigation and dropdown components with emerald theme

#### 📊 Testing and Quality Assurance
- **110 tests passing** (455 assertions) - 100% success rate after cleanup
- **Eliminated flaky conditions**: Removed timing-dependent test scenarios
- **Robust test coverage**: All core business logic thoroughly tested
- **Clean test suite**: No deprecated functionality being tested

#### 🎨 User Experience Improvements
- **Consistent theming**: Emerald color scheme throughout all components
- **Simplified navigation**: Clean, minimal UI without unnecessary complexity
- **Mobile responsive**: All recreated components maintain responsive design
- **Dark mode support**: Complete dark/light theme compatibility

### Added
- WHMCS synchronization feature for users and hosting services
- Soft delete support for users and hostings
- Automatic restoration of soft deleted records when reactivated in WHMCS
- Support for authorized users (delegated access)
- Comprehensive test suite for WHMCS synchronization
- Documentation for WHMCS synchronization action
- **NEW**: Spatie Laravel One-Time Passwords integration
- **NEW**: Spatie Laravel Activity Log for user tracking
- **NEW**: Simplified navigation components with emerald theme

### Changed
- Updated code comments to English following project standards
- Migrated tests from PHPUnit to PEST 3
- Improved error handling and logging
- Enhanced database schema with soft deletes
- **MAJOR**: Authentication system from magic links to OTP codes
- **MAJOR**: Rate limiting from custom system to Spatie OTP integration
- **MAJOR**: Simplified component architecture

### Removed
- **BREAKING**: Laravel Breeze complete authentication scaffolding
- **BREAKING**: Custom magic link system and dependencies
- **BREAKING**: Custom IP failure tracking and rate limiting
- **BREAKING**: Complex authentication flows and unnecessary UI components
- **BREAKING**: Obsolete configuration files and unused dependencies

### Fixed
- Issue with string vs integer type in WHMCS ID comparison
- Hosting synchronization when user is deactivated
- Factory definitions to match current database schema
- **NEW**: Blade component compilation errors after cleanup
- **NEW**: Navigation component dependencies
- **NEW**: Route references to eliminated views

### Security
- Added validation for WHMCS data
- Implemented proper error handling for failed synchronizations
- Protected against unauthorized user access
- **ENHANCED**: OTP-based authentication with IP and User-Agent validation
- **ENHANCED**: Automated session management and security auditing

### Technical Debt Resolved
- ✅ **Eliminated duplicate authentication systems** (magic links vs OTP)
- ✅ **Removed overengineered rate limiting** (custom vs Spatie integration)
- ✅ **Cleaned up unused Breeze scaffolding** (37 files removed)
- ✅ **Simplified component architecture** (minimal essential components)
- ✅ **Unified authentication flow** (single OTP entry point)

### Remaining Technical Debt
- [ ] Add support for synchronizing server configurations from WHMCS
- [ ] Implement batch processing for better performance
- [ ] Add support for custom fields synchronization
- [ ] Implement retry mechanism for failed synchronizations
- [ ] Add monitoring and alerting for failed synchronizations
- [ ] Improve test coverage for edge cases
- [ ] Add performance metrics collection 
