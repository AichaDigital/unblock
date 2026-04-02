# Upgrade Design: Laravel 13 + Livewire 4 + Filament 5

**Date:** 2026-04-02
**Author:** Abdelkarim Mateos
**Status:** Approved (post eng-review)

## Problem Statement

Unblock runs Laravel 12, Livewire 3, and Filament 4. These need upgrading to Laravel 13, Livewire 4, and Filament 5 to stay current, get security patches, and access new features.

## Constraints

- **Dual mode architecture** (Admin Mode + Simple Mode) must work identically after upgrade
- **2 production servers** (amazzal, central) with different PHP/Node setups
- **158 test files** must pass before and after each phase
- **Zero downtime** for session invalidation (fix cache prefixes pre-deploy)

## Approach: 4-Phase Sequential Upgrade

Phases are strictly ordered because Filament 5 requires Livewire 4, which requires Laravel 13.

```
Phase 0 (cleanup) → Phase 1 (L13) → Phase 2 (LW4) → Phase 3 (F5)
         │                  │                │               │
    remove dead deps   framework core   volt removal    filament script
    remove symfony pin  CSRF rename     SFC migration   post-scripts
    remove dead routes  cache prefix    test migration  package cleanup
```

### Phase 0: Pre-upgrade Cleanup

Remove blockers before touching framework versions.

**Dependencies to remove from composer.json:**

| Package | Why | Impact |
|---|---|---|
| `danharrin/livewire-rate-limiting` | Only supports LW3, not used in code | None |
| `graham-campbell/throttle` | Only supports L10-12, not used in code | None |
| `valentin-morice/filament-json-column` | Only supports F4, not used in code | None |
| `laravel/breeze` (dev) | Generates conflicting auth scaffolding | None |
| `symfony/mailer` constraint | Pinned to ~7.3.4, blocks L13 (needs ^7.4) | Laravel manages internally |

**Files to delete:**

- `routes/auth.php` — dead code, not registered in bootstrap/app.php

**Validation:** `composer update` must resolve cleanly.

### Phase 1: Laravel 13

**composer.json changes:**

- `laravel/framework`: `^12.0` → `^13.0`
- `laravel/tinker`: `^2.9` → `^3.0`

**Code changes:**

- `app/Providers/Filament/AdminPanelProvider.php` line 11,44:
  - `VerifyCsrfToken` → `PreventRequestForgery`

**Config changes:**

- `.env.example`: add `CACHE_PREFIX`, `REDIS_PREFIX`, `SESSION_COOKIE` with explicit values

**Validation:** `php artisan test` — all 158 test files must pass.

### Phase 2: Livewire 4 + Volt Removal

**composer.json changes:**

- `livewire/livewire`: `^3.4` → `^4.0`
- Remove `livewire/volt`

**Files to delete:**

- `app/Providers/VoltServiceProvider.php`

**Files to modify:**

1. `bootstrap/providers.php`: remove `VoltServiceProvider::class`

2. `routes/web.php`:
   - Remove `use Livewire\Volt\Volt;`
   - `Volt::route('/', 'otp-login')` → `Route::livewire('/', 'otp-login')`
   - `Volt::route('dashboard', 'unified-dashboard')` → `Route::livewire('dashboard', 'unified-dashboard')`

3. `resources/views/livewire/unified-dashboard.blade.php` (SFC):
   - `use Livewire\Volt\Component;` → `use Livewire\Component;`
   - `#[Layout('components.layouts.app')]` → update to LW4 layout convention

4. `resources/views/livewire/layout/navigation.blade.php` (SFC):
   - Same Volt→Livewire conversion

5. `tests/Feature/Livewire/UnifiedDashboardTest.php`:
   - All `Volt::test('unified-dashboard')` → `Livewire::test(UnifiedDashboard::class)`
   - Remove `use Livewire\Volt\Volt;`, add `use Livewire\Livewire;`

6. `tests/Feature/Livewire/AuthorizedUserDashboardTest.php`:
   - Same Volt::test() → Livewire::test() migration

**wire:model instances:** No changes needed. All 11 instances are on direct `<input>` elements, not parent containers. The LW4 child-event change doesn't affect them.

**Validation:** `php artisan test` — all tests must pass.

### Phase 3: Filament 5

**Steps:**

1. Run `vendor/bin/filament-v5` (automated upgrade script)
2. Update `filament/filament`: `^4.0` → `^5.0`
3. `composer update`
4. Verify/update post-scripts in composer.json:
   - `filament:upgrade` → check if renamed in F5
   - `filament:optimize` / `filament:optimize-clear` → check if changed

**Files affected:** ~47 Filament files (automated by script, manual review after)

**Validation:** `composer check-full` (lint + PHPStan + tests with coverage)

### Pre-deploy Checklist

1. Create git tag `pre-laravel13-upgrade` on current HEAD as rollback point
2. On amazzal and central `.env`: add explicit `CACHE_PREFIX`, `REDIS_PREFIX`, `SESSION_COOKIE`
3. Optional: clean `cdnjs.cloudflare.com` from nginx CSP (was for filament-json-column)

## Dependencies Verified Compatible

| Package | Current | Supports L13? | Supports LW4? |
|---|---|---|---|
| `lorisleiva/laravel-actions` | v2.10.1 | Yes (^11\|^12\|^13) | N/A |
| `spatie/laravel-activitylog` | v4.12+ | Yes | N/A |
| `spatie/laravel-one-time-passwords` | v1.0.11 | Yes (^12\|^13) | Yes (^3.7\|^4.0) |
| `spatie/laravel-honeypot` | v4.6 | Verify during update | N/A |
| `spatie/ssh` | v1.12 | Verify during update | N/A |
| `phpseclib/phpseclib` | v3.0 | Yes | N/A |
| `predis/predis` | v2.2\|v3.0 | Yes | N/A |

## Risk Register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Filament-v5 script misses custom components | Medium | Manual review of 47 files after script |
| Spatie packages need updates beyond minor | Low | composer update -W handles this |
| Layout path breaks in LW4 | Medium | Verify both layouts, test rendering |
| Production sessions invalidated | High (if no mitigation) | Fix .env prefixes BEFORE deploy |

## NOT in scope

- Migration to Symfony 8 (L13 accepts 7.4+)
- Refactoring Filament resources beyond F5 compatibility
- CI/CD pipeline changes
- Tailwind/Vite upgrades (already compatible)
- New features or improvements

## Sources

- [Laravel 13 Upgrade Guide](https://laravel.com/docs/13.x/upgrade)
- [Filament 5 Upgrade Guide](https://filamentphp.com/docs/5.x/upgrade-guide)
- [Livewire 4 Upgrade Guide](https://livewire.laravel.com/docs/4.x/upgrading)
- [Livewire 4 Pages (Route::livewire)](https://livewire.laravel.com/docs/4.x/pages)
