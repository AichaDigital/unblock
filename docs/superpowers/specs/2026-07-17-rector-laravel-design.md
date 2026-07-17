# Rector adoption — gate infrastructure (PR 0)

- **Ticket:** AID-528
- **Date:** 2026-07-17
- **Status:** approved, pending implementation

## Goal

Adopt `rector/rector` + `driftingly/rector-laravel` in unblock as a code-quality support tool, wired to a gate that actually blocks bad merges. Follow the wave-based adoption pattern proven in openmizan (AID-520), **adapted to unblock's measured reality** rather than copied.

Wave-based means: PR 0 ships infrastructure only, with a rule set verified zero-diff. Each subsequent wave enables curated rules in its own mechanical PR, backed by the full suite.

## Measured baseline (pilot, 2026-07-17)

Run in an isolated worktree, zero writes to the repo. `rector/rector 2.5.7` + `driftingly/rector-laravel 2.5.0`, over `app/` (182 PHP files), target `PHP_83`:

| Rule / Set | Files |
|---|---|
| `RemoveDumpDataDeadCodeRector` | **0** ← Wave 0 |
| `ReadOnlyClassRector` | 0 |
| `LARAVEL_TYPE_DECLARATIONS` | 0 |
| `deadCode` (50) | 3 |
| `LARAVEL_COLLECTION` | 4 |
| `codeQuality` (50) | 22 |
| `LARAVEL_CODE_QUALITY` | 28 |
| `AddOverrideAttributeToOverriddenMethodsRector` | 48 |
| `DeclareStrictTypesRector` | 73 |

`DeclareStrictTypesRector`'s 73 is cross-checked independently: `app/` has 182 PHP files, 109 of which already carry `declare(strict_types=1)` — 182 − 109 = 73.

## Why this is not a copy of openmizan's config

Two premises behind openmizan's `rector.php` do not hold here, and both were measured:

1. **`DeclareStrictTypesRector` is not zero-diff in unblock.** openmizan's `pint.json` sets `declare_strict_types: true`, so Pint owns strict-types insertion repo-wide and `app/` sits at 597/597 — the Rector rule there is an inert second lock. unblock's `pint.json` does not set it, and `app/` sits at 109/182. Enabling the same rule here would rewrite 73 files with a **semantic** change (runtime type coercion), not a cosmetic one. Deferred to its own wave.

2. **unblock already has the enforcement openmizan lacks.** openmizan's `main` has no branch protection (its AID-527), which is why its `rector.php` documents enforcement as "ship-workflow convention — a red job does not block a merge by itself". unblock's `main` has `required_status_checks: ["quality"]` with `strict: true` (verified via `gh api`), and the `quality` job runs `composer check-full`. Placing the gate inside `check-full` therefore inherits real enforcement for free: a pending diff turns CI red and blocks the merge.

## Design

### `rector.php`

```php
->withPaths([__DIR__.'/app'])
->withPhpVersion(PhpVersion::PHP_83)
->withRules([RemoveDumpDataDeadCodeRector::class])
->withSkip([
    ThrowIfRector::class,
    ThrowIfAndThrowUnlessExceptionsToUseClassStringRector::class,
    AppToResolveRector::class,
])
```

**Path scope.** `app/` only. `routes/`, `bootstrap/`, `config/` and `tests/` are wave candidates, not oversights. `database/` must never be added: migrations are append-only history.

**PHP version.** `PHP_83` follows the `composer.json` floor (`^8.3|^8.4`). Bump both together so gate semantics never drift implicitly. The CI runtime is PHP 8.4; `withPhpVersion` sets the transformation target, not the runtime, so the two are independent by design.

**Skips**, justified against unblock's own code rather than inherited:

- `ThrowIfRector` — `app/` has 52 explicit `throw new` and **zero** `throw_if()`. The explicit guard *is* the convention here; `throw_if()` would also flatten guards and evaluate its exception argument eagerly even when nothing throws.
- `ThrowIfAndThrowUnlessExceptionsToUseClassStringRector` — inert today (no `throw_if()` exists), armed in advance so the decision lives in config rather than in remembered prose. Rector warns such rules are "never registered" until a set enables them; that warning is accepted deliberately.
- `AppToResolveRector` — `app()` 2 vs `resolve()` 2. Naming churn with no semantic value.

### Wave 0 carries exactly one rule

Three rules measured zero-diff, but only `RemoveDumpDataDeadCodeRector` ships. The other two are excluded on purpose:

- Neither has been proven to bite. A rule that reports nothing and has not been shown to detect anything is noise in the gate, not protection.
- `ReadOnlyClassRector` is a forward risk in a Filament/Livewire codebase, where components mutate properties by design.

`RemoveDumpDataDeadCodeRector` earns its place: it stops a forgotten `dd()` from reaching main, and it is proven to catch one.

### Gate wiring

```
"refactor":     rector process && pint --dirty     # write path
"refactor:dry": rector process --dry-run           # gate path

"check"      += @refactor:dry
"check-full" += @refactor:dry     # job `quality` (required + strict) => red blocks merge
scripts/pre-commit += rector --dry-run step
```

The pre-commit hook does **not** call `check-full` — it runs its own steps — so the gate must be added there explicitly or the hook will not inherit it. The hook runs Rector over all of `app/`, matching how Pint and PHPStan already behave in that hook; at 182 files the cost is negligible next to the test run it already performs.

Ordering inside `check-full`: after `@pint-test`, before `@phpstan`. Style is settled first, so a Rector failure is never a formatting artifact.

## Verification

**Expected code diff: 0 files.** `composer refactor:dry` must exit 0 on the untouched tree.

**Violation probe — the gate must be proven to bite, or it is decorative:**

1. Clean baseline → `rc=0`.
2. Add a new isolated file containing a `dd()` → `rc=2`, and Rector must **name the file and the rule** (`app/ProbeVictim.php:6` / `* RemoveDumpDataDeadCodeRector`).
3. Remove the probe → `rc=0`, tree clean.

The probe must be attributable. During the pilot, a first attempt reported a false "bites" result: the script had corrupted a file with stray whitespace and the `rc=2` came from that, not from the `dd()` — the injection had in fact failed. Asserting on the exit code alone is insufficient; assert that Rector names the file and the rule, and confirm the tree is clean afterwards.

**Full gate:** `composer check-full` green before opening the PR.

## Waves (separate tickets)

| Wave | Content | Files | Nature |
|---|---|---|---|
| 1 | `strict_types` + `pint.json` | 73 in `app/` | **Semantic** — changes runtime coercion |
| 2 | `AddOverrideAttribute` | 48 | Mechanical |
| 3 | `LARAVEL_CODE_QUALITY` + `codeQuality` 50 | 28 + 22 | Mechanical |
| 4 | `LARAVEL_COLLECTION` + `deadCode` 50 | 4 + 3 | Manual review of public signatures |

Wave 1 carries a scope trap worth recording now: unblock's `pint.json` excludes `database`, `elk`, `config` and `notas`, but **not** `tests/`. Setting `declare_strict_types: true` in Pint would therefore rewrite the 175 files under `tests/` and `routes/` as well, not just the 73 under `app/`. Size that wave before starting it.

Each wave must re-prove its new rules bite before landing.
