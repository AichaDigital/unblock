# Proyecto: unblock

## Información del Proyecto
- **Nombre**: unblock
- **Tipo**: laravel (Laravel 12 + Livewire + Filament)
- **Ruta**: /Users/abkrim/SitesLR12/unblock
- **Configurado**: 2025-07-09
- **Última actualización**: 2025-11-05

## Importar Preferencias Globales
@~/.claude/CLAUDE.md

---

## 🎭 ARQUITECTURA DUAL MODE (CRÍTICO)

Esta aplicación opera en **DOS MODOS diferentes**. Esta es la característica más importante del proyecto.

### Modo 1: ADMIN MODE (`simple_mode.enabled = false`)
- Panel de administración completo
- Login con email + OTP de usuarios reales/administradores
- Acceso completo a dashboard (`/dashboard`)
- Gestión de hosts, dominios, usuarios, firewall
- Panel Filament activado
- Usuarios persistentes en base de datos
- Redirección post-login: `route('dashboard')`

### Modo 2: SIMPLE MODE (`simple_mode.enabled = true`)
- Sistema de autoservicio público para desbloqueo rápido
- Login simplificado: dominio + email + IP
- Acceso SOLO a formulario de desbloqueo (`/simple-unblock`)
- **NO acceso** al dashboard ni panel Filament
- Usuarios temporales (`first_name='Simple', last_name='Unblock'`)
- Rate limiting agresivo (multi-vector)
- Whitelist temporal (1 hora por defecto)
- Redirección post-login: `route('simple.unblock')`

### ⚠️ Middlewares Críticos
1. **`simple.mode.enabled`**: Protege `/simple-unblock` cuando simple mode está desactivado (retorna 404)
2. **`simple.mode`**: Controla acceso de usuarios temporales vs admin (bloquea usuarios temporales del dashboard)
3. **`throttle.simple.unblock`**: Rate limiting multi-vector para prevenir ataques

### 🔥 REGLA DE ORO: NUNCA ASUMIR EL MODO
- En tests: **SIEMPRE** usar `config()->set('unblock.simple_mode.enabled', true/false)`
- En código: **SIEMPRE** verificar `config('unblock.simple_mode.enabled')`
- Cada feature que afecte auth/routing/middleware DEBE tener tests para ambos modos

---

## 🧪 TESTING STANDARDS

### Regla Crítica: Configuración Explícita
Los tests **NO deben depender de `.env.testing`**. Cada test debe configurar explícitamente los parámetros con `config()->set()`.

### Tests Obligatorios para Features con Dual Mode
```php
// 1. Test con Simple Mode ENABLED
test('feature X works in simple mode', function () {
    config()->set('unblock.simple_mode.enabled', true);
    // Test específico
});

// 2. Test con Admin Mode
test('feature X works in admin mode', function () {
    config()->set('unblock.simple_mode.enabled', false);
    // Test específico
});

// 3. Test de protección de acceso
test('feature X respects mode restrictions', function () {
    config()->set('unblock.simple_mode.enabled', false);
    get('/simple-unblock')->assertNotFound(); // 404, no 403
});
```

### Helper de Testing Disponible
- `SimpleModeTestHelper::enableSimpleMode()`
- `SimpleModeTestHelper::disableSimpleMode()`
- Funciones globales Pest: `enableSimpleMode()`, `disableSimpleMode()`

### 🔥 REGLAS DE ORO PARA DATOS SENSIBLES EN TESTS

**⚠️ NUNCA, JAMÁS, BAJO NINGUNA CIRCUNSTANCIA:**
1. NO uses passwords reales en tests
2. NO uses API keys reales en tests
3. NO uses tokens reales en tests
4. NO uses credenciales de servicios reales
5. NO uses datos de tarjetas de crédito reales

**✅ LO QUE SÍ PUEDES HACER:**
```php
// ✅ CORRECTO - Claramente marcado como test
$testPassword = 'TestP@ssw0rd!123456'; // ggignore

// ✅ CORRECTO - API key fake
$fakeApiKey = 'test_stripe_key_fake_123abc'; // ggignore

// ✅ CORRECTO - Token fake
$testToken = 'test_jwt_token_not_real_xyz789'; // ggignore
```

**🛡️ PROTECCIÓN:**
- GitGuardian SÍ escanea los tests
- Solo ignora passwords documentados en `.gitguardian.yaml`
- Usa prefijo 'Test' o 'Fake' en todo dato sensible
- Cada dato fake debe tener comentario `// ggignore`

---

## 🛡️ SECURITY STANDARDS

### 1. Manejo de Credenciales
- No almacenar claves SSH o contraseñas directamente
- Usar Laravel Vault o mecanismos seguros
- No hardcodear valores sensibles, usar variables de entorno
- Implementar rotación periódica de credenciales

### 2. Validación de Entradas
- Validar y sanear todas las entradas de usuario (especialmente IPs y comandos)
- Usar Form Requests para validación compleja
- Implementar rate limiting en endpoints sensibles

### 3. Control de Acceso
- Usar Policies para verificar permisos
- Seguir el principio de mínimo privilegio
- Implementar multi-factor authentication para administradores

### 4. Auditoría y Logging
- Registrar todos los eventos de seguridad
- Incluir contexto completo en logs (usuario, IP, acción)
- Usar canales de log separados para eventos de seguridad

---

## 🚀 WORKFLOW Y CALIDAD DE CÓDIGO

### Proceso de Desarrollo (Senior Engineer Standard)

#### 1. Clarificar Scope Primero
- Antes de escribir código, mapear el enfoque
- Confirmar interpretación del objetivo
- Escribir plan claro de qué módulos se tocarán y por qué
- No comenzar hasta razonar el plan completo

#### 2. Localizar Punto de Inserción Exacto
- Identificar archivo(s) y línea(s) precisas
- Nunca hacer edits masivos en archivos no relacionados
- Justificar explícitamente cada archivo incluido
- No crear nuevas abstracciones/refactors salvo que se solicite

#### 3. Cambios Mínimos y Contenidos
- Solo escribir código directamente requerido
- Evitar agregar logging, comments, tests, TODOs, cleanup innecesarios
- No hacer cambios especulativos o "ya que estamos aquí"
- Aislar toda lógica para no romper flujos existentes

#### 4. Double Check Everything
- Revisar correctitud, adherencia al scope, efectos secundarios
- Asegurar alineación con patrones existentes
- Verificar explícitamente impacto downstream

#### 5. Entregar Claramente
- Resumir qué cambió y por qué
- Listar cada archivo modificado y qué se hizo
- Flagear cualquier asunción o riesgo

### Antes de Cualquier Commit

**OBLIGATORIO ejecutar**: `composer check-full`

Este comando verifica:
- Lint (Pint)
- PHPStan
- Coverage
- El proyecto tiene reglas muy estrictas

### Ejecución de Tests

**Tests Parciales**: Al terminar un refactor o nuevo aspecto
- Ejecutar tests afectados
- Nunca dar por terminado sin ejecutar tests

**Tests Completos**: Siempre antes de commit
- `php artisan test` completo
- Los tests no pasan si uno solo falla

---

## 📂 ESTRUCTURA Y ESTÁNDARES

### Base de Datos
- SQLite como base de datos principal
- Estructura documentada en `database/sql_prompts/unblock.sqlite.sql`
- Comando de generación: `php artisan develop:sqlite-structure`

### Estructura del Proyecto
- Documentada en `instructions/structure.txt`
- Generada por: `scripts/generate-structure.sh`
- Actualizar después de cambios significativos

### Reglas de Cursor
Las reglas completas de desarrollo están en `.cursor/rules/`:
- `main.mdc` - Regla principal con referencias
- `unblock-dual-mode-architecture.mdc` - **Arquitectura dual (CRÍTICO)**
- `testing-standards.mdc` - Estándares de testing
- `security-standards.mdc` - Estándares de seguridad
- `workflow.mdc` - Flujo de trabajo
- `senior-engineer-task-execution-rule.mdc` - Estándares de ejecución

**Consultar estas reglas** ante cualquier duda sobre implementación.

---

## 🚨 CHECKLIST PRE-REFACTORIZACIÓN

Antes de cualquier refactorización, verificar:

- [ ] ¿La feature afecta autenticación? → Probar ambos modos
- [ ] ¿La feature afecta routing? → Verificar middlewares de modo
- [ ] ¿La feature afecta emails? → Diferenciar templates por modo
- [ ] ¿Hay nuevas rutas? → Aplicar middleware correcto según modo
- [ ] ¿Hay nuevos tests? → Configurar modo explícitamente
- [ ] ¿Se modifica lógica de usuario? → Considerar usuarios temporales vs reales
- [ ] ¿Se modifica OTP? → Verificar redirección post-login según modo
- [ ] **¿Se ejecutó `composer check-full`?** → Obligatorio antes de commit
- [ ] **¿Se ejecutaron TODOS los tests?** → `php artisan test` antes de commit

---

## 📝 COMPONENTES CRÍTICOS

### Actions
- `SimpleUnblockAction` - Solo ejecuta si simple mode enabled
- `CreateSimpleUnblockReportAction` - Solo en simple mode

### Jobs
- `ProcessSimpleUnblockJob` - Solo se despacha si simple mode enabled

### Livewire Components
- `OtpLogin` - Redirección diferente según modo (`isSimpleMode()`)
- `SimpleUnblockForm` - Solo accesible en simple mode

### Middlewares (Ver sección Dual Mode arriba)
- `CheckSimpleModeEnabled` (`simple.mode.enabled`)
- `SimpleModeAccess` (`simple.mode`)
- `ThrottleSimpleUnblock` (`throttle.simple.unblock`)

---

## 🚢 CI/CD

### Pipeline

- **CI** (`.github/workflows/ci.yml`): Se ejecuta en PRs a main. Lint + PHPStan + tests con coverage >= 80% + `composer audit`
- **CD** (`.github/workflows/deploy.yml`): Se ejecuta en push a main (post-merge). Deploy secuencial con health check

### Flujo de deploy

```
Merge a main → CI (quality gate) → Deploy amazzal → Health check → Deploy central → Health check
```

### Servidores de producción

| | amazzal | central |
|---|---|---|
| **URL** | unblock.castris.com | desbloquear.xerintel.es |
| **User** | `laravel` | `unblock` |
| **Ruta** | `/home/laravel/domains/unblock.castris.com/unblock/` | `/home/unblock/unblock/` |
| **Panel** | DirectAdmin | cPanel |
| **Queue** | Redis + Supervisor | Redis + Supervisor |

### Pasos de deploy (por servidor)

1. `git pull origin main`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `php artisan config:cache && route:cache && view:cache && event:cache`
6. `php artisan queue:restart`

### Health check

- URL: `/admin/login` (ruta Filament, **NO** `/login`)
- Espera HTTP 200

### Secrets (GitHub)

- `DEPLOY_SSH_KEY` — Clave ed25519 para deploy
- `AMAZZAL_HOST`, `AMAZZAL_PORT`, `CENTRAL_HOST`, `CENTRAL_PORT`

---

## ⚡ REFERENCIAS RÁPIDAS

### Configuración
- Config principal: `config/unblock.php` líneas 73-93
- Simple mode: `config('unblock.simple_mode.enabled')`

### Tests de Referencia (Buenos ejemplos)
- `tests/Unit/Middleware/CheckSimpleModeEnabledTest.php`
- `tests/Feature/SimpleUnblock/SimpleUnblockFormTest.php`
- `tests/Feature/SimpleUnblock/SimpleUnblockQueueConfigTest.php`

### Comandos Útiles
- `php artisan develop:sqlite-structure` - Regenerar estructura DB
- `scripts/generate-structure.sh` - Regenerar estructura proyecto
- `composer check-full` - Verificación completa pre-commit
- `php artisan test` - Tests completos

---

## ✅ RESUMEN EJECUTIVO (TL;DR)

1. **DOS MODOS**: Admin Mode y Simple Mode
2. **CONTROL**: `config('unblock.simple_mode.enabled')`
3. **AFECTA TODO**: Auth, routing, middleware, emails, tests
4. **EN TESTS**: SIEMPRE configurar modo explícitamente
5. **NUNCA ASUMIR**: El modo en código o tests
6. **SIEMPRE PROBAR**: Ambos modos cuando desarrolles features
7. **PRE-COMMIT**: `composer check-full` + `php artisan test`
8. **SEGURIDAD**: NO usar datos reales en tests, prefijo 'Test' o 'Fake' + `// ggignore`

**Si ignoras la dualidad de modos o los estándares de testing, romperás la aplicación. Sin excepciones.**

---

*Última actualización: 2026-03-06*
*Versión: 2.1.0*
*Mantenedor: Equipo de desarrollo Unblock*

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v4
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `livewire-development` — Use for any task or question involving Livewire. Actovate if user mentions Livewire, wire: directives, or Livewire-specific concepts like wire:model, wire:click, invoke this skill. Covers building new components, debugging reactivity issues, real-time form validation, loading states, migrating from Livewire 2 to 3, converting component formats (SFC/MFC/class-based), and performance optimization. Do not use for non-Livewire reactive UI (React, Vue, Alpine-only, Inertia.js) or standard Laravel forms without Livewire.
- `volt-development` — Develops single-file Livewire components with Volt. Activates when creating Volt components, converting Livewire to Volt, working with @volt directive, functional or class-based Volt APIs; or when the user mentions Volt, single-file components, functional Livewire, or inline component logic in Blade files.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `pnpm run build`, `pnpm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
