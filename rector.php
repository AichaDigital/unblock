<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\FuncCall\{AppToResolveRector, RemoveDumpDataDeadCodeRector, ThrowIfAndThrowUnlessExceptionsToUseClassStringRector};
use RectorLaravel\Rector\If_\ThrowIfRector;

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
 * Convention ownership: unlike openmizan, this repo's pint.json does NOT set
 * `declare_strict_types`, and app/ sits at 109/182. DeclareStrictTypesRector
 * is therefore deliberately absent — enabling it is a semantic change over 73
 * files (runtime type coercion) and belongs to its own wave, not here.
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
    ])
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
