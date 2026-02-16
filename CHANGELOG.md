# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Dependency bumps: axios 1.13.5, vite 7.3.1, laravel-vite-plugin 2.1.0, filament/upgrade, laravel/boost, debugbar, infection

## [1.4.0] - 2026-02-16

### Added

- Context logs (exim/dovecot auth failures) in not-blocked reports, collected regardless of block status
- LFD history: queries `/var/log/lfd.log` for recent block/unblock events as context
- Recent block history from database: detects timing gaps (IP checked between temporary blocks)
- SSH error sentinel pattern `[SSH_ERROR:command]` replaces silent empty strings
- `CheckRecentBlockHistoryAction` for querying database block history
- Email template sections for context logs, recent history and SSH warnings in not-blocked reports
- 25 new unit tests (897 total, 2163 assertions)

### Fixed

- CpanelFirewallAnalyzer: exim/dovecot logs now collected always, not only when IP is blocked
- All grep-based DirectAdmin commands include `|| true` to prevent false SSH failures
- SSH failures no longer silently return empty strings

## [1.3.1] - 2026-01-03

### Added

- Admin OTP 2FA for panel access
- Complete internationalization system ES/EN for Filament resources and OTP
- Custom logo upload with application-wide settings system
- HQ whitelist email with professional design and documentation
- Admin panel access whitelist
- Account suspension toggle and edit functionality
- Cooldown and visual feedback for Simple Mode submissions

### Changed

- Test coverage increased from 51.1% to 75.0% with quality-focused tests
- Frontend and email templates UX/UI overhaul
- Full testing suite stabilization and refactoring
- Session management refactor with SSH improvements

### Fixed

- SQLite compatibility and GeoIP path resolution
- Session state inconsistencies in OTP authentication flow
- Division by zero in SimpleUnblockOverviewWidget
- Firewall email translations not rendering
- JSON query error in accounts search
- HQ host: unblock IPs before adding to whitelist
- Admin access to unified dashboard restored
- Robust null checks for SimpleUnblock edge cases
- `UNBLOCK_SIMPLE_MODE` duality issues
- ThrottleSimpleUnblock config values cast to int

### Security

- Configure Laravel Boost to prevent credential exposure in production

## [1.3.0] - 2025-11-02

### Added

- Reputation System: IP and email reputation tracking with automatic scoring (0-100)
- Admin Dashboard: Filament resources for IP reputation, email reputation and abuse incidents
- Analytics & Pattern Detection: event-driven architecture with 7 events and 3 queued listeners
- Dashboard overview widget with 6 real-time statistics cards
- Simple Mode improvements with enhanced cross-validation
- OTP cleanup command (`simple-unblock:cleanup-otp`) scheduled daily

### Changed

- Upgraded to Filament 4.1 and Tailwind CSS 4.1

### Fixed

- DirectAdmin BFM blacklist detection: exact IP matching prevents false positives
- BFM whitelist TTL system with database tracking and automatic expiration

## [1.2.0] - 2025-10-23

### Added

- OTP email verification for Simple Unblock Mode (two-step flow)
- IP binding: OTP codes bound to requesting IP to prevent relay attacks
- Warmup migrations for reputation tables (ip_reputation, email_reputation, abuse_incidents)

### Changed

- SimpleUnblockForm rewritten for two-step OTP flow with visual progress indicator

## [1.1.1] - 2025-10-23

### Added

- Anti-bot defense layer: honeypot fields, request fingerprinting, pattern detection
- Multi-vector rate limiting: per IP, email, domain, subnet (/24) and global

### Fixed

- IP spoofing vulnerability: now uses `request()->ip()` respecting TrustProxies

### Security

- GDPR compliance: email hashed (SHA-256) in activity logs instead of plaintext

## [1.1.0] - 2025-10-22

### Added

- Simple Unblock Mode: anonymous IP unblock without authentication
- Public form with cross-validation (IP blocked + domain in server logs)
- Rate limiting (3 req/min per IP), email notifications, activity logging
- Support for both cPanel and DirectAdmin panels
- Anonymous user system for database referential integrity

### Fixed

- Missing database files (factories, migrations, seeders)

### Security

- SSH keys moved from `.ssh/` to `storage/app/.ssh/`

## [1.0.0] - 2025-10-20

### Added

- Initial release of Unblock Firewall Manager
- Multi-firewall analysis: CSF, BFM, Exim, Dovecot, ModSecurity
- FilamentPHP admin panel with user and hosting management
- Optional WHMCS integration for user/hosting sync
- OTP-based authentication (Spatie Laravel One-Time Passwords)
- Bilingual interface EN/ES
- 257 tests, 94% coverage

[Unreleased]: https://github.com/AichaDigital/unblock/compare/v1.4.0...HEAD
[1.4.0]: https://github.com/AichaDigital/unblock/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/AichaDigital/unblock/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/AichaDigital/unblock/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/AichaDigital/unblock/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/AichaDigital/unblock/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/AichaDigital/unblock/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/AichaDigital/unblock/releases/tag/v1.0.0
