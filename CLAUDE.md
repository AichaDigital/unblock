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
