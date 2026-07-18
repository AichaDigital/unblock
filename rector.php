<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector;
use Rector\CodeQuality\Rector\If_\{CombineIfRector, ExplicitBoolCompareRector, ObjectExplicitBoolCompareRector, SimplifyIfReturnBoolRector};
use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\ClassMethod\MakeModelAttributesAndScopesProtectedRector;
use RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector;
use RectorLaravel\Rector\FuncCall\{AppToResolveRector, NowFuncWithStartOfDayMethodCallToTodayFuncRector, RemoveDumpDataDeadCodeRector, ThrowIfAndThrowUnlessExceptionsToUseClassStringRector};
use RectorLaravel\Rector\If_\ThrowIfRector;
use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;
use RectorLaravel\Set\LaravelSetList;

/**
 * Rector gate — wave-based adoption (AID-528).
 *
 * CI runs `composer refactor:dry` as part of `composer check-full`, which is
 * what the `quality` job executes. That job is a required status check on
 * main with strict mode, so a pending diff turns CI red and blocks the merge.
 * Rules are enabled in curated waves — each wave is its own mechanical PR
 * verified by the full suite, and each wave must re-prove its new rules bite
 * (violation probe → non-zero exit) before the wave PR lands.
 *
 * Version coupling: rector/rector hard-requires phpstan/phpstan (^2.2.2 as of
 * 2.5.7), so rector, larastan and phpstan move as ONE unit. Run `composer why
 * phpstan/phpstan` on every rector bump and watch for a minor dragging phpstan
 * forward — that would silently change the `@phpstan` gate inside the same PR.
 * Renovate is active on this repo, so the bump arrives unattended. Adopting
 * rector did not move it (phpstan stayed at 2.2.5, larastan at v3.10.0); the
 * risk is the next upgrade, not this one. (Learned from openmizan AID-526.)
 *
 * Path scope (deliberate, per AID-528):
 * - app/ only for now. routes/, bootstrap/, config/ and tests/ are wave
 *   candidates, not oversights — extending scope is a wave decision.
 * - database/ must NEVER be added: migrations are append-only history.
 *
 * Wave 0 carries exactly one rule. Three rules measured zero-diff in the
 * pilot (RemoveDumpDataDeadCodeRector, ReadOnlyClassRector and the
 * LARAVEL_TYPE_DECLARATIONS set), but only this one is proven to bite; a rule
 * that reports nothing and has not been shown to detect anything is noise in
 * the gate, not protection. ReadOnlyClassRector is additionally a forward
 * risk here: Filament/Livewire components mutate properties by design.
 *
 * Convention ownership (AID-529): unlike openmizan, this repo's pint.json does
 * NOT set `declare_strict_types` — and it stays that way on purpose. The gate
 * owns the convention for app/: DeclareStrictTypesRector below makes a new
 * file without the declare fail `refactor:dry`, so Pint is not needed to
 * enforce it here. Pint would only add reach OUTSIDE app/ (measured at
 * adoption: tests/ 44 files short of the convention, routes/ 2, bootstrap/ 6)
 * — that is a separate wave decision with a different mechanism and a
 * different risk profile, not a side effect of this one.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
    ])
    // Follows the composer.json "php" floor (^8.3|^8.4). Bump together with the
    // composer constraint so gate semantics never drift implicitly. This sets
    // the transformation target, not the runtime — CI runs PHP 8.4.
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withRules([
        RemoveDumpDataDeadCodeRector::class,
        // Wave 2 (AID-530). Both PHP 8.3, satisfied by the PhpVersion pin
        // above. Measured with --clear-cache: 48 + 9 files, overlap zero.
        AddOverrideAttributeToOverriddenMethodsRector::class,
        AddTypeToConstRector::class,
        // Wave 1 (AID-529), landed last: the only semantic rule here, since
        // strict_types changes runtime type coercion rather than shape.
        DeclareStrictTypesRector::class,
        // Wave 3 (AID-531). ONLY the equivalence-safe cosmetics from
        // LARAVEL_CODE_QUALITY + codeQuality level 50. The rest of that set
        // is NOT mechanical and was split off by risk profile:
        //   - MakeModelAttributesAndScopesProtectedRector (27 scopes
        //     public→protected, public surface) → AID-540
        //   - CarbonToDateFacadeRector + NowFunc...ToTodayFunc (runtime class
        //     + temporal semantics) → AID-541
        //   - ApplyDefaultInsteadOfNullCoalesceRector (?? vs ?: — differs on
        //     false/0/'') → AID-542
        //   - DispatchToHelperFunctions / RedirectRouteToToRoute /
        //     CompactToVariables / ThrowWithPreviousException (equivalence
        //     must be shown per transform) → AID-543
        // Each transform below was diff-verified equivalence-preserving; the
        // ExplicitBoolCompare rewrites were confirmed to fire only on
        // string-typed vars (Rector left the ?string option() path alone).
        // NB: measure/apply waves with `rector --clear-cache` — the cache does
        // not discriminate the rule set, so `composer refactor` after a local
        // measuring session can under-apply (16 files measured, 3 applied).
        ObjectExplicitBoolCompareRector::class,
        ExplicitBoolCompareRector::class,
        SimplifyIfReturnBoolRector::class,
        StrlenZeroToIdenticalEmptyStringRector::class,
        CombineIfRector::class,
        // Wave 4 (AID-532). ReadOnlyClassRector was zero-diff at PR 0 but
        // unproven; adopted here after both probes passed: it bites on an
        // all-readonly app class, and it leaves a Livewire component alone
        // even when all its properties are readonly (a readonly class cannot
        // extend a non-readonly parent, and the rule honours that) — the
        // Filament/Livewire risk flagged at PR 0 is empirically refuted.
        ReadOnlyClassRector::class,
        // Wave 5 (AID-540), split from AID-531 as a public-surface change:
        // 27 scopes across 6 models flip public→protected. Safe here because
        // (a) zero direct ->scopeX()/::scopeX() calls and zero dynamic
        // string references repo-wide, and (b) the Eloquent proxy invokes
        // scopes via Model::callNamedScope() -> $this->{'scope'.ucfirst(...)}
        // from the base class of the hierarchy (vendor Model.php), so
        // protected visibility never blocks it — Filament included, since it
        // consumes scopes through the builder proxy. This app is final, not a
        // library: no external consumers exist.
        MakeModelAttributesAndScopesProtectedRector::class,
        // Wave 6 (AID-542), split from AID-531 over the ??/?: lesson. The
        // rule turns `helper('key') ?? $default` into `helper('key',
        // $default)` — NOT ??→?:, but it still has a non-equivalent corner:
        // a key present with an explicit null returns $default under ??,
        // null under the parameter form. Both sites it flagged used
        // `config('unblock.hq.ttl') ?? 7200`, where that corner is
        // unreachable by construction (config/unblock.php casts `(int)
        // env(..., 14000)`, so the key always exists as int) — which also
        // made the 7200 a dead phantom default lying about the real 14000.
        // The fallbacks were removed by hand (the default's single owner is
        // the config file); the rule stays on as the guard against future
        // `config('x') ?? $d`, which is always either dead or a duplicated
        // default. If a legitimate present-but-null key ever appears, skip
        // that call site here rather than dropping the rule.
        ApplyDefaultInsteadOfNullCoalesceRector::class,
        // Wave 7 (AID-541), split from AID-531 over temporal semantics. Both
        // proven equivalent HERE, not assumed: no Date::use()/DateFactory
        // config exists, so the Date facade fabricates Carbon\Carbon — the
        // runtime class does not change at all; zero `instanceof Carbon`
        // repo-wide; and every converted value is consumed immediately
        // (->toDateTimeString(), ->greaterThan(), SQL comparison), the object
        // never escapes. today() ≡ now()->startOfDay() under the app's single
        // timezone (UTC), and both honour Carbon::setTestNow(). The facade is
        // the Laravel idiom and keeps Date::use() available as a future seam.
        CarbonToDateFacadeRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
    ])
    // Wave 4 (AID-532): LARAVEL_COLLECTION (4 sites, all element types are
    // scalars/plain arrays so toArray()≡all(); filter(!empty)→reject(empty)
    // moves the negation without touching the predicate) and
    // LARAVEL_TYPE_DECLARATIONS (zero-diff, proven to bite via
    // EloquentWhereTypeHintClosureParameterRector — the Builder hints help
    // larastan). Dead-code level 50: the two SimplifyUselessVariable hits are
    // trivial; RemoveUnusedPrivateProperty on FirewallService was verified
    // dead repo-wide (case-insensitive grep, zero callers of the orphan
    // setter, property write-only) — the leftover empty public setter was
    // removed by hand in the same wave, since Rector only empties it.
    ->withSets([
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ])
    ->withDeadCodeLevel(50)
    ->withSkip([
        // Excluded by design decision (AID-528), armed BEFORE any wave enables
        // a set that would register them — config, not remembered prose.
        // Rector warns these rules are "never registered" until such a set
        // lands; that warning is accepted deliberately.
        //
        // app/ holds 52 explicit `throw new` and zero `throw_if()`: the
        // explicit guard is the convention. throw_if() also flattens guards and
        // evaluates its exception argument eagerly even when nothing is thrown.
        ThrowIfRector::class,
        ThrowIfAndThrowUnlessExceptionsToUseClassStringRector::class,
        // app() vs resolve() is naming churn with no semantic value (2 vs 2).
        AppToResolveRector::class,
    ]);
