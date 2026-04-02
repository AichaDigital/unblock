# Laravel 13 + Livewire 4 + Filament 5 Upgrade Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade unblock from Laravel 12 + Livewire 3 + Filament 4 to Laravel 13 + Livewire 4 + Filament 5.

**Architecture:** 4-phase sequential upgrade. Each phase must pass `php artisan test` before proceeding to the next. Phases are ordered by dependency chain: cleanup → L13 → LW4 → F5.

**Tech Stack:** Laravel 13, Livewire 4, Filament 5, Pest 4, PHPStan, SQLite, Redis

**Spec:** `docs/superpowers/specs/2026-04-02-laravel13-filament5-upgrade-design.md`

**CRITICAL — Dual Mode Architecture:** This app has Admin Mode (`simple_mode.enabled=false`) and Simple Mode (`simple_mode.enabled=true`). Both modes MUST work after each phase. Tests cover both modes.

---

## Phase 0: Pre-upgrade Cleanup

### Task 1: Remove dead dependencies

**Files:**

- Modify: `composer.json`

- [ ] **Step 1: Remove 4 dead require packages and symfony/mailer pin**

Edit `composer.json`. Remove these lines from `"require"`:

```json
"danharrin/livewire-rate-limiting": "^2.0",
"graham-campbell/throttle": "^11",
"symfony/mailer": "~7.3.4",
```

Remove this line from `"require"`:

```json
"valentin-morice/filament-json-column": "dev-dev"
```

Remove this line from `"require-dev"`:

```json
"laravel/breeze": "^2.2",
```

- [ ] **Step 2: Run composer update to verify resolution**

Run: `composer update danharrin/livewire-rate-limiting graham-campbell/throttle valentin-morice/filament-json-column laravel/breeze symfony/mailer --with-all-dependencies`

Expected: Packages removed successfully, no errors.

- [ ] **Step 3: Verify tests still pass**

Run: `php artisan test`

Expected: All tests pass (the removed packages were unused in code).

- [ ] **Step 4: Commit**

```bash
git add composer.json
git commit -m "chore: remove dead dependencies blocking upgrade

Remove danharrin/livewire-rate-limiting (LW3 only, unused),
graham-campbell/throttle (L10-12 only, unused),
valentin-morice/filament-json-column (F4 only, unused),
laravel/breeze (generates conflicting auth scaffolding),
symfony/mailer pin (was ~7.3.4, blocks L13 which needs ^7.4).

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 2: Remove dead routes file

**Files:**

- Delete: `routes/auth.php`

- [ ] **Step 1: Delete routes/auth.php**

```bash
rm routes/auth.php
```

This file is not registered in `bootstrap/app.php` (only `routes/web.php` is). It contains a Volt::route for login that duplicates the one in web.php. Dead code.

- [ ] **Step 2: Verify tests still pass**

Run: `php artisan test`

Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove dead routes/auth.php (not registered in bootstrap/app.php)

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Phase 1: Laravel 13

### Task 3: Upgrade Laravel framework to v13

**Files:**

- Modify: `composer.json`

- [ ] **Step 1: Update version constraints in composer.json**

In `composer.json`, change in `"require"`:

```json
"laravel/framework": "^13.0",
"laravel/tinker": "^3.0",
```

And change in `"require-dev"`:

```json
"nunomaduro/collision": "^9.0",
```

Note: Collision v9 is required by Laravel 13. The current `^8.0` won't satisfy L13 requirements.

- [ ] **Step 2: Run composer update**

Run: `composer update laravel/framework laravel/tinker nunomaduro/collision --with-all-dependencies`

Expected: Resolves to Laravel 13.x, Tinker 3.x, Collision 9.x. If any other dependency blocks resolution, check the error output and update that constraint too.

- [ ] **Step 3: Verify the app boots**

Run: `php artisan --version`

Expected: `Laravel Framework 13.x.x`

- [ ] **Step 4: Run tests**

Run: `php artisan test`

Expected: All tests pass. If tests fail, review the failure output — most likely a minor API change caught by the test suite.

- [ ] **Step 5: Commit**

```bash
git add composer.json
git commit -m "feat: upgrade Laravel 12 → 13

Update laravel/framework ^13.0, laravel/tinker ^3.0,
nunomaduro/collision ^9.0.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 4: Replace VerifyCsrfToken with PreventRequestForgery

**Files:**

- Modify: `app/Providers/Filament/AdminPanelProvider.php:11,44`

- [ ] **Step 1: Update the import and usage**

In `app/Providers/Filament/AdminPanelProvider.php`, replace:

```php
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
```

with:

```php
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
```

And in the `->middleware([...])` array on line 44, replace:

```php
VerifyCsrfToken::class,
```

with:

```php
PreventRequestForgery::class,
```

- [ ] **Step 2: Run tests**

Run: `php artisan test`

Expected: All tests pass. The old name works as a deprecated alias in L13, but we migrate proactively.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "refactor: rename VerifyCsrfToken → PreventRequestForgery (L13)

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 5: Fix cache prefix and session cookie in .env.example

**Files:**

- Modify: `.env.example`

- [ ] **Step 1: Add explicit prefix values to .env.example**

In `.env.example`, find the Session & Cache section and update it to include explicit values that match the L12 format (underscores, not hyphens). This prevents session invalidation on deploy:

Replace:

```
CACHE_PREFIX=
```

with:

```
CACHE_PREFIX=unblock_firewall_manager_cache_
SESSION_COOKIE=unblock_firewall_manager_session
```

These values use the old underscore format to maintain backward compatibility with existing sessions in production.

- [ ] **Step 2: Commit**

```bash
git add .env.example
git commit -m "chore: add explicit cache prefix and session cookie to .env.example

Prevents session invalidation when upgrading to L13 (which changes
default prefix format from underscores to hyphens).

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Phase 2: Livewire 4 + Volt Removal

### Task 6: Upgrade Livewire and remove Volt package

**Files:**

- Modify: `composer.json`

- [ ] **Step 1: Update Livewire and remove Volt from composer.json**

In `composer.json`, change in `"require"`:

```json
"livewire/livewire": "^4.0",
```

And remove from `"require"`:

```json
"livewire/volt": "^1.6",
```

- [ ] **Step 2: Run composer update**

Run: `composer update livewire/livewire --with-all-dependencies && composer remove livewire/volt`

Expected: Livewire 4.x installed, Volt removed.

**NOTE:** Do NOT run `php artisan test` yet — the Volt references in code will cause failures. We fix those in the next tasks.

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "chore: upgrade Livewire 3 → 4 and remove Volt package

Livewire 4 absorbs Volt SFC support natively.
Code migration follows in next commits.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 7: Remove VoltServiceProvider

**Files:**

- Delete: `app/Providers/VoltServiceProvider.php`
- Modify: `bootstrap/providers.php:9`

- [ ] **Step 1: Delete VoltServiceProvider**

```bash
rm app/Providers/VoltServiceProvider.php
```

- [ ] **Step 2: Remove from bootstrap/providers.php**

In `bootstrap/providers.php`, remove the line:

```php
    App\Providers\VoltServiceProvider::class,
```

The file should look like:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\RouteServiceProvider::class,
];
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove VoltServiceProvider (Volt removed in LW4)

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 8: Migrate routes from Volt::route to Route::livewire

**Files:**

- Modify: `routes/web.php`

- [ ] **Step 1: Rewrite routes/web.php**

Replace the entire content of `routes/web.php` with:

```php
<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Ruta principal '/' - Sistema OTP Login usando componente Livewire
Route::livewire('/', 'otp-login')
    ->middleware(['guest', 'throttle:10,1'])
    ->name('login');

// Rutas protegidas
Route::livewire('dashboard', 'unified-dashboard')
    ->middleware(['auth', 'session.timeout', 'simple.mode'])
    ->name('dashboard');

// Rutas de utilidad
Route::get('/report/{id}', ReportController::class)->name('report.show');

// Simple Unblock Mode (always register route, middleware will handle access control)
Route::get('/simple-unblock', \App\Livewire\SimpleUnblockForm::class)
    ->middleware(['simple.mode.enabled', 'throttle.simple.unblock'])
    ->name('simple.unblock');

// Admin OTP Verification
Route::get('/admin/otp/verify', \App\Livewire\AdminOtpVerification::class)
    ->middleware(['web', 'auth'])
    ->name('admin.otp.verify');
```

Changes: removed `use Livewire\Volt\Volt;`, changed 2x `Volt::route()` to `Route::livewire()`.

- [ ] **Step 2: Commit**

```bash
git add routes/web.php
git commit -m "refactor: migrate Volt::route → Route::livewire in web.php

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 9: Convert unified-dashboard SFC from Volt to Livewire

**Files:**

- Modify: `resources/views/livewire/unified-dashboard.blade.php:2-15`

- [ ] **Step 1: Update the PHP block at the top of the SFC**

In `resources/views/livewire/unified-dashboard.blade.php`, change line 4:

```php
use Livewire\Volt\Component;
```

to:

```php
use Livewire\Component;
```

And change line 13 from:

```php
#[Layout('components.layouts.app')]
```

to:

```php
#[Layout('layouts.app')]
```

Note: Livewire 4 looks for layouts in `resources/views/layouts/` by default. The project has `resources/views/layouts/app.blade.php` which is the correct target. If after testing the layout renders incorrectly (wrong styling/theme), you may need to either: (a) copy the content from `resources/views/components/layouts/app.blade.php` into `resources/views/layouts/app.blade.php`, or (b) use `#[Layout('components.layouts.app')]` if Livewire 4 still supports the old path. Test visually after this step.

- [ ] **Step 2: Commit**

```bash
git add resources/views/livewire/unified-dashboard.blade.php
git commit -m "refactor: convert unified-dashboard SFC from Volt to Livewire component

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 10: Convert navigation SFC from Volt to Livewire

**Files:**

- Modify: `resources/views/livewire/layout/navigation.blade.php:4`

- [ ] **Step 1: Update the Volt import**

In `resources/views/livewire/layout/navigation.blade.php`, change line 4:

```php
use Livewire\Volt\Component;
```

to:

```php
use Livewire\Component;
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/livewire/layout/navigation.blade.php
git commit -m "refactor: convert navigation SFC from Volt to Livewire component

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 11: Migrate Volt::test to Livewire::test in UnifiedDashboardTest

**Files:**

- Modify: `tests/Feature/Livewire/UnifiedDashboardTest.php`

- [ ] **Step 1: Replace imports**

In `tests/Feature/Livewire/UnifiedDashboardTest.php`, replace line 4:

```php
use Livewire\Volt\Volt;
```

with:

```php
use Livewire\Livewire;
```

- [ ] **Step 2: Replace all Volt::test calls**

Run a global find-and-replace in the file:

Find: `Volt::test('unified-dashboard')`
Replace: `Livewire::test('unified-dashboard')`

There are 19 instances on lines: 68, 96, 131, 150, 161, 181, 187, 203, 220, 245, 264, 280, 311, 330, 374, 405, 439, 483, 509.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Livewire/UnifiedDashboardTest.php
git commit -m "test: migrate Volt::test → Livewire::test in UnifiedDashboardTest

19 test calls updated.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 12: Migrate Volt::test to Livewire::test in AuthorizedUserDashboardTest

**Files:**

- Modify: `tests/Feature/Livewire/AuthorizedUserDashboardTest.php`

- [ ] **Step 1: Replace imports**

In `tests/Feature/Livewire/AuthorizedUserDashboardTest.php`, replace line 6:

```php
use Livewire\Volt\Volt;
```

with:

```php
use Livewire\Livewire;
```

- [ ] **Step 2: Replace all Volt::test calls**

Run a global find-and-replace in the file:

Find: `Volt::test('unified-dashboard')`
Replace: `Livewire::test('unified-dashboard')`

There are 17 instances on lines: 52, 82, 124, 165, 191, 233, 280, 306, 313, 351, 405, 424, 436, 472, 478, 488, 497.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Livewire/AuthorizedUserDashboardTest.php
git commit -m "test: migrate Volt::test → Livewire::test in AuthorizedUserDashboardTest

17 test calls updated.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 13: Verify all Livewire 4 changes pass tests

**Files:** None (verification only)

- [ ] **Step 1: Run full test suite**

Run: `php artisan test`

Expected: All 158 test files pass.

If tests fail:

- **"Class unified-dashboard not found"** → The SFC component name resolution changed. Try `Livewire::test(\App\Livewire\UnifiedDashboard::class)` if LW4 requires class-based references for SFCs. Check `php artisan livewire:discover` or equivalent.
- **"Layout not found"** → The layout path `layouts.app` doesn't resolve. Verify `resources/views/layouts/app.blade.php` exists and has the correct content (DaisyUI theme, Vite assets). If the main layout is in `components/layouts/app.blade.php`, copy it to `layouts/app.blade.php`.
- **Route errors** → Verify `Route::livewire()` is available in LW4. Check `php artisan route:list`.

- [ ] **Step 2: Fix any failures**

Address failures based on the guidance above. Commit each fix individually.

---

## Phase 3: Filament 5

### Task 14: Run the Filament v5 automated upgrade script

**Files:**

- Multiple Filament files (automated by script)

- [ ] **Step 1: Verify filament/upgrade is installed**

Run: `composer show filament/upgrade`

Expected: Shows version ^5.2 (already in require-dev).

- [ ] **Step 2: Run the upgrade script**

Run: `vendor/bin/filament-v5`

The script will analyze your codebase and output commands to run. **Read the output carefully** before executing the suggested commands.

Expected: The script outputs a series of changes and commands. Follow its instructions.

- [ ] **Step 3: Execute the commands output by the script**

Run whatever the script tells you to run. These are unique to your application. Common outputs include:

- Namespace changes
- Method renames
- Config updates
- View publishing commands

- [ ] **Step 4: Commit the automated changes**

```bash
git add -A
git commit -m "refactor: apply Filament v5 automated upgrade script changes

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 15: Update Filament package version

**Files:**

- Modify: `composer.json`

- [ ] **Step 1: Update filament constraint**

In `composer.json`, change in `"require"`:

```json
"filament/filament": "^5.0",
```

- [ ] **Step 2: Run composer update**

Run: `composer require filament/filament:"^5.0" -W`

Expected: Filament 5.x installed with all its dependencies (including Livewire 4 which is already present).

- [ ] **Step 3: Remove the upgrade tool**

Run: `composer remove filament/upgrade --dev`

It's no longer needed after the upgrade.

- [ ] **Step 4: Commit**

```bash
git add composer.json
git commit -m "feat: upgrade Filament 4 → 5

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 16: Verify and update composer.json post-scripts

**Files:**

- Modify: `composer.json` (if needed)

- [ ] **Step 1: Verify Filament artisan commands still exist**

Run:

```bash
php artisan list | grep filament
```

Check that `filament:upgrade`, `filament:optimize`, and `filament:optimize-clear` still exist in F5. If any command was renamed, the script output from Task 14 should have mentioned it.

- [ ] **Step 2: Fix post-scripts if needed**

In `composer.json`, the following post-scripts reference Filament commands:

- Line 65: `"@php artisan filament:upgrade"` in `post-autoload-dump`
- Lines 70-71: `filament:optimize-clear` and `filament:optimize` in `post-update-cmd`
- Lines 160-161: Same in `deploy:prepare`

If any of these commands were renamed in F5, update the script references to match.

- [ ] **Step 3: Commit if changes were made**

```bash
git add composer.json
git commit -m "chore: update composer post-scripts for Filament 5

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

### Task 17: Final verification — full quality check

**Files:** None (verification only)

- [ ] **Step 1: Run Pint (lint)**

Run: `./vendor/bin/pint`

Fix any formatting issues introduced by the upgrade.

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse --memory-limit=1G`

Fix any type errors. Common issues:

- Filament 5 method signatures changed
- Livewire 4 component return types
- Removed class references (Volt)

- [ ] **Step 3: Run full test suite with coverage**

Run: `composer check-full`

This runs: syntax check → pint --test → phpstan → test with coverage.

Expected: All checks pass, coverage >= 80%.

- [ ] **Step 4: Fix any failures and commit**

Each fix should be a separate atomic commit with a descriptive message.

- [ ] **Step 5: Final commit — squash lint/PHPStan fixes if needed**

```bash
git add -A
git commit -m "fix: resolve lint and PHPStan issues after L13+LW4+F5 upgrade

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Phase 4: Pre-deploy Preparation

### Task 18: Tag release and prepare deploy config

**Files:** None (git and ops only)

- [ ] **Step 1: Create git tag as rollback point**

Run this BEFORE merging the upgrade branch:

```bash
git tag pre-laravel13-upgrade
```

- [ ] **Step 2: Document .env changes for production**

The following must be added to `.env` on both amazzal and central BEFORE deploying the upgrade:

```env
CACHE_PREFIX=unblock_firewall_manager_cache_
SESSION_COOKIE=unblock_firewall_manager_session
```

This prevents session invalidation when L13 changes the default prefix format.

- [ ] **Step 3: Create PR**

```bash
gh pr create --title "feat: upgrade Laravel 13 + Livewire 4 + Filament 5" --body "$(cat <<'EOF'
## Summary
- Upgrade Laravel 12 → 13, Livewire 3 → 4, Filament 4 → 5
- Remove 5 dead dependencies (livewire-rate-limiting, throttle, filament-json-column, breeze, symfony/mailer pin)
- Migrate Volt SFCs to native Livewire 4 SFC support
- Migrate 36 Volt::test() to Livewire::test()
- Delete dead routes/auth.php and VoltServiceProvider

## Pre-deploy checklist
- [ ] Add CACHE_PREFIX and SESSION_COOKIE to .env on amazzal
- [ ] Add CACHE_PREFIX and SESSION_COOKIE to .env on central
- [ ] Optionally clean cdnjs.cloudflare.com from nginx CSP on amazzal

## Test plan
- [ ] `composer check-full` passes locally
- [ ] CI pipeline passes
- [ ] Both Admin Mode and Simple Mode tested
- [ ] Filament admin panel loads correctly
- [ ] OTP login flow works
- [ ] Dashboard renders correctly

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
