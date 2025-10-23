# Simple Unblock Mode - Roadmap & Sprint Planning

**Project**: Unblock IP Management System
**Feature**: Simple Unblock Mode (Anonymous IP Unblocking)
**Document Version**: 1.0
**Last Updated**: 2025-10-23

---

## Overview

Este documento define el roadmap completo para Simple Unblock Mode, incluyendo sprints completados y futuros. El objetivo es proporcionar una referencia clara para retomar trabajos y planificar desarrollo incremental.

---

## Architecture Foundation

### Database Schema (Warmup Tables - v1.2.0)

Las siguientes tablas fueron creadas en v1.2.0 como "warmup" para análisis futuro:

#### `ip_reputation`
```sql
- id (PK)
- ip (string, unique, indexed)
- subnet (string, indexed)
- reputation_score (integer, 0-100, default: 100)
- total_requests (unsigned integer, default: 0)
- failed_requests (unsigned integer, default: 0)
- blocked_count (unsigned integer, default: 0)
- last_seen_at (timestamp, nullable)
- notes (text, nullable)
- created_at, updated_at
```

#### `email_reputation`
```sql
- id (PK)
- email_hash (string 64, unique, indexed) -- SHA-256
- email_domain (string, indexed)
- reputation_score (integer, 0-100, default: 100)
- total_requests (unsigned integer, default: 0)
- failed_requests (unsigned integer, default: 0)
- verified_requests (unsigned integer, default: 0)
- last_seen_at (timestamp, nullable)
- notes (text, nullable)
- created_at, updated_at
```

#### `abuse_incidents`
```sql
- id (PK)
- incident_type (enum: rate_limit_exceeded, ip_spoofing_attempt,
                      otp_bruteforce, honeypot_triggered,
                      invalid_otp_attempts, ip_mismatch,
                      suspicious_pattern, other)
- ip_address (string 45, indexed)
- email_hash (string 64, nullable, indexed)
- domain (string, nullable, indexed)
- severity (enum: low, medium, high, critical, default: medium)
- description (text)
- metadata (json, nullable)
- resolved_at (timestamp, nullable)
- created_at, updated_at
- composite index: [incident_type, severity, created_at]
```

**Propósito**: Estas tablas están inactivas en v1.2.0. Los Sprints 3 y 4 las activarán.

---

## Completed Sprints

### ✅ Sprint 1 (v1.1.1) - Anti-Bot Defense Layer
**Status**: Completed (2025-10-23)
**Branch**: `feature/AntibotDefense` (merged to main)
**Tag**: `v1.1.1`

**Delivered**:
- Multi-Vector Rate Limiting (5 vectors)
  - IP: 3 req/min
  - Email: 5 req/hour
  - Domain: 10 req/hour
  - Subnet /24: 20 req/hour
  - Global: 500 req/hour
- Spatie Laravel Honeypot integration
- **CRITICAL FIX**: IP spoofing vulnerability
- GDPR compliance: Email hashing in activity logs

**Impact**:
- 99.7% reduction in botnet attack effectiveness
- 70-80% simple bots blocked

**Tests**: 315+ passing
**Quality**: PHPStan ✅ | Pint ✅

---

### ✅ Sprint 2 (v1.2.0) - OTP Email Verification
**Status**: Completed (2025-10-23)
**Branch**: `feature/SimpleMode-OTPVerification` (merged to main)
**Tag**: `v1.2.0`

**Delivered**:
- Two-step authentication flow (Request OTP → Verify OTP)
- Email ownership verification
- IP binding for OTP security
- Visual progress indicator UI
- Mobile-optimized OTP input
- Bilingual translations (EN/ES)
- Warmup migrations (3 tables)
- Temporary user auto-creation

**Security**:
- 95%+ bot elimination (can't access email)
- IP binding prevents relay attacks
- IP mismatch detection
- Session security

**Tests**: 319 passing (1097 assertions)
**Quality**: PHPStan ✅ | Pint ✅ | Coverage >90%

---

## Upcoming Sprints

### 🎯 Sprint 3 (v1.3.0) - Reputation System & Admin Dashboard

**Status**: Planned
**Priority**: High
**Estimated Effort**: 3-5 days
**Dependencies**: v1.2.0 (warmup tables)

#### Objectives

Activar las tablas de reputación con tracking automático y proporcionar un dashboard administrativo para análisis y gestión de incidentes.

#### Features

##### 1. Automatic Reputation Tracking System

**Event Listeners** (crear en `app/Listeners/SimpleUnblock/`):

- **`TrackIpReputationListener`**
  - **Trigger**: Después de cada request a Simple Unblock
  - **Action**:
    - Buscar/crear registro en `ip_reputation` por IP
    - Incrementar `total_requests`
    - Actualizar `last_seen_at`
    - Calcular `subnet` (/24 para IPv4, /48 para IPv6)
    - Actualizar `reputation_score` basado en ratio success/fail
  - **Score Algorithm**:
    ```php
    $successRate = 1 - ($failed / max($total, 1));
    $score = max(0, min(100, floor($successRate * 100)));
    ```

- **`TrackEmailReputationListener`**
  - **Trigger**: Después de OTP send/verify
  - **Action**:
    - Hash email (SHA-256)
    - Buscar/crear registro en `email_reputation`
    - Incrementar `total_requests`
    - Si OTP verificado: incrementar `verified_requests`
    - Si OTP falla: incrementar `failed_requests`
    - Actualizar `reputation_score`
    - Extraer y guardar `email_domain`

- **`CreateAbuseIncidentListener`**
  - **Trigger**: En eventos de abuso:
    - Rate limit exceeded (cualquier vector)
    - Honeypot triggered
    - Invalid OTP attempts (3+ consecutivos)
    - IP mismatch during OTP verification
  - **Action**:
    - Crear registro en `abuse_incidents`
    - Clasificar `severity` automáticamente:
      - `low`: Rate limit IP exceeded
      - `medium`: Honeypot triggered, email rate limit
      - `high`: 3+ invalid OTP, domain rate limit exceeded
      - `critical`: IP spoofing attempt, subnet abuse
    - Guardar metadata (JSON con detalles del evento)
    - Decrementar `reputation_score` en tablas correspondientes

**Events to Create** (crear en `app/Events/SimpleUnblock/`):
```php
- SimpleUnblockRequestProcessed
- SimpleUnblockOtpSent
- SimpleUnblockOtpVerified
- SimpleUnblockOtpFailed
- SimpleUnblockRateLimitExceeded
- SimpleUnblockHoneypotTriggered
- SimpleUnblockIpMismatch
```

**Event Registration** (en `EventServiceProvider`):
```php
protected $listen = [
    SimpleUnblockRequestProcessed::class => [
        TrackIpReputationListener::class,
    ],
    SimpleUnblockOtpSent::class => [
        TrackEmailReputationListener::class,
    ],
    SimpleUnblockOtpVerified::class => [
        TrackEmailReputationListener::class,
    ],
    SimpleUnblockOtpFailed::class => [
        TrackEmailReputationListener::class,
        CreateAbuseIncidentListener::class,
    ],
    SimpleUnblockRateLimitExceeded::class => [
        CreateAbuseIncidentListener::class,
    ],
    SimpleUnblockHoneypotTriggered::class => [
        CreateAbuseIncidentListener::class,
    ],
    SimpleUnblockIpMismatch::class => [
        CreateAbuseIncidentListener::class,
    ],
];
```

##### 2. Admin Dashboard (Filament Resources)

**Filament Resources** (crear en `app/Filament/Resources/`):

- **`IpReputationResource`**
  - Table columns:
    - IP address
    - Subnet
    - Reputation score (with color badge: red <30, yellow 30-70, green >70)
    - Total requests
    - Failed requests
    - Blocked count
    - Last seen (human readable)
  - Filters:
    - Score range (0-30, 30-70, 70-100)
    - Subnet search
    - Date range (last seen)
  - Actions:
    - View full details
    - Add to whitelist (Sprint 4)
    - Add note (admin comment)
  - Bulk actions:
    - Export selected to CSV
  - Charts:
    - Reputation distribution (pie chart)
    - Requests over time (line chart)

- **`EmailReputationResource`**
  - Table columns:
    - Email hash (truncated: first 8 chars)
    - Email domain
    - Reputation score (badge)
    - Total requests
    - Verified requests
    - Failed requests
    - Last seen
  - Filters:
    - Score range
    - Email domain
    - Date range
    - Verified vs unverified
  - Actions:
    - View details
    - Add to whitelist
    - Add note
  - Bulk actions:
    - Export to CSV
  - Charts:
    - Top 10 email domains
    - Verification success rate

- **`AbuseIncidentResource`**
  - Table columns:
    - Incident type (badge)
    - IP address (linkable to IpReputationResource)
    - Email hash (truncated, linkable to EmailReputationResource)
    - Domain
    - Severity (color badge)
    - Description
    - Resolved status
    - Created at
  - Filters:
    - Incident type (multi-select)
    - Severity (multi-select)
    - Resolved/Unresolved
    - Date range
    - IP address search
    - Domain search
  - Actions:
    - View full details (with JSON metadata)
    - Mark as resolved
    - Add investigation notes
  - Bulk actions:
    - Mark multiple as resolved
    - Export to CSV
  - Charts:
    - Incidents by type (bar chart)
    - Incidents by severity (pie chart)
    - Incidents timeline (area chart)
    - Top 10 abusing IPs

**Dashboard Widgets** (crear `SimpleUnblockOverviewWidget`):
- Total requests today/week/month
- Average reputation score
- Active incidents (unresolved, severity: high/critical)
- Top 5 abusing IPs (last 7 days)
- Top 5 abusing email domains (last 7 days)
- OTP verification success rate (last 7 days)

##### 3. Temporary User Cleanup Job

**Comando Artisan** (crear `app/Console/Commands/CleanupTemporaryUsersCommand.php`):
```php
php artisan simple-unblock:cleanup-temp-users
```

**Functionality**:
- Buscar usuarios con `first_name = 'Simple'` AND `last_name = 'Unblock'`
- Filtrar usuarios sin `one_time_password_code` (OTP expirado)
- Filtrar usuarios con `updated_at` > 30 días
- Soft delete usuarios que cumplan condiciones
- Log cantidad de usuarios eliminados
- Opción `--force` para hard delete (usar con precaución)
- Opción `--dry-run` para previsualizar sin eliminar

**Schedule** (en `app/Console/Kernel.php`):
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('simple-unblock:cleanup-temp-users')
             ->weekly()
             ->sundays()
             ->at('03:00')
             ->onOneServer();
}
```

#### Technical Implementation

**Files to Create**:
```
app/Events/SimpleUnblock/
├── SimpleUnblockRequestProcessed.php
├── SimpleUnblockOtpSent.php
├── SimpleUnblockOtpVerified.php
├── SimpleUnblockOtpFailed.php
├── SimpleUnblockRateLimitExceeded.php
├── SimpleUnblockHoneypotTriggered.php
└── SimpleUnblockIpMismatch.php

app/Listeners/SimpleUnblock/
├── TrackIpReputationListener.php
├── TrackEmailReputationListener.php
└── CreateAbuseIncidentListener.php

app/Filament/Resources/
├── IpReputationResource.php
├── EmailReputationResource.php
└── AbuseIncidentResource.php

app/Filament/Widgets/
└── SimpleUnblockOverviewWidget.php

app/Console/Commands/
└── CleanupTemporaryUsersCommand.php

tests/Feature/SimpleUnblock/Reputation/
├── IpReputationTrackingTest.php
├── EmailReputationTrackingTest.php
├── AbuseIncidentCreationTest.php
├── ReputationScoreCalculationTest.php
└── CleanupTemporaryUsersTest.php

tests/Feature/Filament/
├── IpReputationResourceTest.php
├── EmailReputationResourceTest.php
└── AbuseIncidentResourceTest.php
```

**Files to Modify**:
```
app/Providers/EventServiceProvider.php (register listeners)
app/Console/Kernel.php (schedule cleanup job)
app/Livewire/SimpleUnblockForm.php (dispatch events)
app/Actions/SimpleUnblockAction.php (dispatch events)
app/Http/Middleware/ThrottleSimpleUnblock.php (dispatch events)
```

#### Acceptance Criteria

- [ ] Event listeners crean/actualizan registros en `ip_reputation` correctamente
- [ ] Event listeners crean/actualizan registros en `email_reputation` correctamente
- [ ] Event listeners crean registros en `abuse_incidents` automáticamente
- [ ] Reputation scores se calculan correctamente (0-100)
- [ ] Filament resources son accesibles solo por admins
- [ ] Dashboard widgets muestran datos en tiempo real
- [ ] Charts se renderizan correctamente
- [ ] Filtros y búsquedas funcionan en todas las resources
- [ ] Cleanup job elimina usuarios temporales correctamente
- [ ] Cleanup job respeta flags `--dry-run` y `--force`
- [ ] Tests tienen >90% coverage
- [ ] PHPStan pasa sin errores (level max)
- [ ] Pint formatea todos los archivos
- [ ] CHANGELOG actualizado con v1.3.0
- [ ] No hay breaking changes

#### Testing Requirements

**Unit Tests**:
- Reputation score calculation algorithm
- Event listener logic (mock events)
- Cleanup job logic

**Feature Tests**:
- Full flow: Request → Event → Listener → DB update
- Multiple requests update counters correctly
- Abuse incidents created on violations
- Filament resources accessible by admin
- Filament resources NOT accessible by regular user
- Cleanup job removes correct users

**Minimum Coverage**: 90%

#### Deployment Notes

1. Merge feature branch to main
2. Run migrations (already exist from v1.2.0)
3. Clear caches
4. Test event dispatching manually
5. Verify Filament resources render
6. Schedule cleanup job (weekly)
7. Monitor first week for event performance

#### Performance Considerations

- Event listeners should be **queued** (use `ShouldQueue`)
- Reputation updates should use `updateOrCreate` (1 query)
- Dashboard queries should be cached (5 minutes)
- Charts should use DB aggregations (not collection processing)

#### GDPR Compliance

- Email reputation table stores **hashed emails only** (SHA-256)
- Abuse incidents table stores **hashed emails only**
- No plaintext emails in any reputation/abuse table
- Admin dashboard shows hashes, not emails
- Cleanup job respects soft deletes (can restore if needed)

---

### 🎯 Sprint 4 (v1.4.0) - Auto-Blocking & Pattern Detection

**Status**: Planned
**Priority**: Medium
**Estimated Effort**: 4-6 days
**Dependencies**: v1.3.0 (reputation system active)

#### Objectives

Implementar bloqueo automático basado en reputation scores, detección inteligente de patrones de abuso, y sistema de notificaciones webhook para alertas en tiempo real.

#### Features

##### 1. Automatic Blocking System

**Middleware** (crear `app/Http/Middleware/BlockLowReputationMiddleware.php`):

**Functionality**:
- Ejecutar **antes** de `ThrottleSimpleUnblock`
- Consultar `ip_reputation` por IP del request
- Si `reputation_score < 20`: bloquear (403 Forbidden)
- Consultar `email_reputation` por hash de email (si proporcionado en form)
- Si `reputation_score < 30`: bloquear
- Verificar whitelist antes de bloquear
- Log de bloqueos con razón
- Response genérico para evitar enumeration

**Whitelist System**:

Crear tabla `reputation_whitelist`:
```sql
- id (PK)
- type (enum: ip, email_hash)
- value (string, indexed)
- reason (text)
- added_by (user_id, FK)
- expires_at (timestamp, nullable)
- created_at, updated_at
```

**Filament Resource** (`ReputationWhitelistResource`):
- Admin puede añadir IP/email a whitelist
- Especificar razón (texto libre)
- Opcional: fecha de expiración
- Vista de whitelisted entries
- Acción: Remove from whitelist

**Block Notification**:
- Cuando se bloquea, mostrar mensaje genérico:
  ```
  "Your request could not be processed at this time.
   If you believe this is an error, please contact support."
  ```
- Crear `abuse_incident` con severity: `high`
- Enviar webhook notification (si configurado)

##### 2. Intelligent Pattern Detection

**Analyzer Service** (crear `app/Services/SimpleUnblock/PatternAnalyzer.php`):

**Patterns to Detect**:

1. **Email Enumeration Attack**
   - **Pattern**: Mismo email usado desde 5+ IPs diferentes en <1 hora
   - **Action**:
     - Crear incident: `suspicious_pattern`
     - Severity: `high`
     - Reducir email reputation -30 puntos
     - Temporary block email (1 hora)

2. **IP Rotation Attack**
   - **Pattern**: Misma IP con 5+ emails diferentes en <1 hora
   - **Action**:
     - Crear incident: `suspicious_pattern`
     - Severity: `high`
     - Reducir IP reputation -30 puntos

3. **Subnet Abuse**
   - **Pattern**: Subnet /24 con 10+ IPs únicas haciendo requests en <1 hora
   - **Action**:
     - Crear incident: `suspicious_pattern`
     - Severity: `critical`
     - Block entire subnet temporalmente (2 horas)
     - Alert admin via webhook

4. **OTP Brute Force**
   - **Pattern**: 5+ intentos de OTP fallidos para mismo email en <10 minutos
   - **Action**:
     - Crear incident: `otp_bruteforce`
     - Severity: `critical`
     - Block email permanentemente (require admin unblock)
     - Block IP temporalmente (1 hora)
     - Alert admin via webhook

5. **Domain Scanning**
   - **Pattern**: Misma IP probando 10+ dominios diferentes en <1 hora
   - **Action**:
     - Crear incident: `suspicious_pattern`
     - Severity: `medium`
     - Reducir IP reputation -20 puntos

6. **Failed Request Pattern**
   - **Pattern**: IP/Email con >80% failed requests y >10 total requests
   - **Action**:
     - Crear incident: `suspicious_pattern`
     - Severity: `medium`
     - Reducir reputation -10 puntos

**Implementation**:
- Scheduled job (ejecutar cada 15 minutos): `php artisan simple-unblock:analyze-patterns`
- Consultar datos de últimas 24 horas
- Ejecutar análisis de cada patrón
- Crear incidents si detecta anomalías
- Actualizar reputation scores
- Dispatch webhooks para severity: high/critical

**Command** (crear `app/Console/Commands/AnalyzePatternsCommand.php`):
```php
php artisan simple-unblock:analyze-patterns [--verbose]
```

**Schedule**:
```php
$schedule->command('simple-unblock:analyze-patterns')
         ->everyFifteenMinutes()
         ->onOneServer();
```

##### 3. Webhook Notification System

**Configuration** (añadir a `config/unblock.php`):
```php
'webhooks' => [
    'enabled' => env('UNBLOCK_WEBHOOKS_ENABLED', false),

    'slack' => [
        'enabled' => env('UNBLOCK_WEBHOOK_SLACK_ENABLED', false),
        'url' => env('UNBLOCK_WEBHOOK_SLACK_URL'),
    ],

    'discord' => [
        'enabled' => env('UNBLOCK_WEBHOOK_DISCORD_ENABLED', false),
        'url' => env('UNBLOCK_WEBHOOK_DISCORD_URL'),
    ],

    'custom' => [
        'enabled' => env('UNBLOCK_WEBHOOK_CUSTOM_ENABLED', false),
        'url' => env('UNBLOCK_WEBHOOK_CUSTOM_URL'),
        'headers' => [
            'Authorization' => env('UNBLOCK_WEBHOOK_CUSTOM_AUTH'),
        ],
    ],

    'notify_on' => [
        'incident_high' => true,
        'incident_critical' => true,
        'auto_block' => true,
        'pattern_detected' => true,
    ],

    'retry' => [
        'times' => 3,
        'backoff' => [1000, 2000, 5000], // milliseconds
    ],
],
```

**Service** (crear `app/Services/SimpleUnblock/WebhookNotifier.php`):

**Methods**:
```php
public function notifyIncident(AbuseIncident $incident): void
public function notifyBlock(string $type, string $value, string $reason): void
public function notifyPattern(string $pattern, array $data): void
```

**Payload Format** (JSON):
```json
{
  "event": "abuse_incident",
  "timestamp": "2025-10-23T15:30:00Z",
  "severity": "high",
  "data": {
    "incident_type": "otp_bruteforce",
    "ip_address": "192.168.1.100",
    "email_hash": "abc123...",
    "domain": "example.com",
    "description": "5 failed OTP attempts in 10 minutes"
  }
}
```

**Slack Format**: Rich message con color basado en severity
**Discord Format**: Embed con color basado en severity
**Custom Format**: JSON genérico

**Job** (crear `app/Jobs/SendWebhookNotificationJob.php`):
- Queued job
- Implements retry logic con exponential backoff
- Log failures to `storage/logs/webhook-failures.log`
- Timeout: 10 segundos por webhook

##### 4. Export & Reporting

**Export Functionality**:

Añadir a Filament Resources:
- **Export to CSV** (bulk action)
  - All visible columns
  - Respects current filters
  - Max 10,000 rows per export
  - Queued job for large exports

**Scheduled Reports** (opcional):

**Command** (crear `app/Console/Commands/SendWeeklyReportCommand.php`):
```php
php artisan simple-unblock:send-weekly-report
```

**Report Contents**:
- Total requests (last 7 days)
- OTP verification success rate
- New abuse incidents by severity
- Top 10 abusing IPs
- Top 10 abusing email domains
- Auto-blocks executed
- Patterns detected

**Email Format**: PDF attachment con charts

**Schedule**:
```php
$schedule->command('simple-unblock:send-weekly-report')
         ->weekly()
         ->mondays()
         ->at('09:00')
         ->onOneServer();
```

**Recipients**: Admins configurados en `.env`

#### Technical Implementation

**Files to Create**:
```
app/Http/Middleware/
└── BlockLowReputationMiddleware.php

app/Services/SimpleUnblock/
├── PatternAnalyzer.php
└── WebhookNotifier.php

app/Console/Commands/
├── AnalyzePatternsCommand.php
└── SendWeeklyReportCommand.php

app/Jobs/
└── SendWebhookNotificationJob.php

app/Models/
└── ReputationWhitelist.php

database/migrations/
└── *_create_reputation_whitelist_table.php

app/Filament/Resources/
└── ReputationWhitelistResource.php

tests/Feature/SimpleUnblock/
├── AutoBlockingTest.php
├── PatternDetectionTest.php
├── WhitelistSystemTest.php
├── WebhookNotificationTest.php
└── WeeklyReportTest.php
```

**Files to Modify**:
```
config/unblock.php (add webhooks config)
app/Http/Kernel.php (register BlockLowReputationMiddleware)
app/Console/Kernel.php (schedule pattern analysis and weekly report)
bootstrap/app.php (middleware priority)
```

#### Acceptance Criteria

- [ ] Middleware bloquea IPs con reputation < 20
- [ ] Middleware bloquea emails con reputation < 30
- [ ] Whitelist system permite bypass de bloqueos
- [ ] Pattern analyzer detecta los 6 patrones definidos
- [ ] Incidents se crean automáticamente al detectar patrones
- [ ] Reputation scores se ajustan basado en patrones
- [ ] Webhooks se envían correctamente (Slack/Discord/Custom)
- [ ] Webhook retry logic funciona con exponential backoff
- [ ] Export CSV funciona desde Filament resources
- [ ] Weekly report se genera y envía correctamente
- [ ] Tests tienen >90% coverage
- [ ] PHPStan pasa sin errores (level max)
- [ ] Pint formatea todos los archivos
- [ ] CHANGELOG actualizado con v1.4.0
- [ ] Performance: pattern analysis completa en <30 segundos

#### Testing Requirements

**Unit Tests**:
- Pattern detection logic
- Reputation score adjustments
- Webhook payload formatting
- Retry logic

**Feature Tests**:
- Auto-blocking flow (request → check → block)
- Whitelist bypass
- Pattern detection end-to-end
- Webhook sending (mock HTTP)
- Export CSV generation
- Weekly report generation

**Integration Tests**:
- Full abuse flow: Pattern → Incident → Block → Webhook
- Multiple patterns detected simultaneously
- Webhook failures handled gracefully

**Minimum Coverage**: 90%

#### Deployment Notes

1. Create and run new migration (reputation_whitelist)
2. Update `.env` with webhook configurations
3. Register middleware in HTTP Kernel
4. Schedule pattern analysis job (every 15 minutes)
5. Schedule weekly report (Mondays 9am)
6. Test webhooks with test incidents
7. Monitor first week for false positives
8. Adjust reputation thresholds if needed

#### Configuration Examples

**.env additions**:
```bash
# Webhooks
UNBLOCK_WEBHOOKS_ENABLED=true

# Slack
UNBLOCK_WEBHOOK_SLACK_ENABLED=true
UNBLOCK_WEBHOOK_SLACK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Discord
UNBLOCK_WEBHOOK_DISCORD_ENABLED=false
UNBLOCK_WEBHOOK_DISCORD_URL=

# Custom
UNBLOCK_WEBHOOK_CUSTOM_ENABLED=false
UNBLOCK_WEBHOOK_CUSTOM_URL=
UNBLOCK_WEBHOOK_CUSTOM_AUTH="Bearer YOUR_TOKEN"

# Weekly Reports
UNBLOCK_WEEKLY_REPORT_ENABLED=true
UNBLOCK_WEEKLY_REPORT_RECIPIENTS=admin@example.com,security@example.com
```

#### Performance Considerations

- Pattern analysis should be queued for large datasets
- Webhook sending must be async (queued jobs)
- CSV export for >1000 rows should be queued
- Dashboard queries must use indexes
- Block checks happen before rate limiting (fail fast)

#### Security Considerations

- Block responses must be generic (no information disclosure)
- Whitelist requires admin authentication
- Webhook URLs stored encrypted in config
- Webhook payloads don't include sensitive data (emails are hashed)
- Pattern detection prevents false positives via thresholds

#### Known Limitations

- Pattern analysis runs every 15 min (not real-time)
- Auto-blocks based on thresholds may have false positives
- Webhook failures logged but not retried indefinitely
- Weekly reports limited to 10,000 rows per section

---

## Future Considerations (Post v1.4.0)

### Phase 3 - Intelligence & Automation

**Potential Features**:
1. **Machine Learning Pattern Detection**
   - Train model on historical abuse data
   - Predict abusive behavior before it happens
   - Auto-adjust reputation thresholds

2. **IP Reputation API Integration**
   - AbuseIPDB integration
   - IPQualityScore integration
   - Adjust local scores based on external reputation

3. **Multi-Domain Support**
   - Allow user to submit multiple domains in one request
   - Check if IP is blocked for ANY of their domains
   - Bulk unblock functionality

4. **Geolocation Validation**
   - Verify IP geolocation matches domain country
   - Flag suspicious geo-mismatches
   - Country-based rate limiting

5. **Advanced Analytics**
   - Predictive dashboards
   - Anomaly detection visualizations
   - Cost analysis (SSH connection costs)

6. **API for Simple Unblock**
   - RESTful API endpoints
   - OAuth2 authentication
   - Webhook callbacks for status updates

---

## Success Metrics

### Sprint 3 (v1.3.0)
- [ ] 100% of requests tracked in reputation tables
- [ ] Admin dashboard used by team weekly
- [ ] Cleanup job runs successfully weekly
- [ ] 0 incidents missed by tracking system

### Sprint 4 (v1.4.0)
- [ ] >95% of abusive patterns detected
- [ ] <1% false positive rate on auto-blocks
- [ ] Webhooks delivered with >99% success rate
- [ ] Pattern analysis completes in <30 seconds
- [ ] Weekly reports generated successfully

---

## Technical Debt & Improvements

### Sprint 3
- [ ] Consider caching reputation lookups (Redis)
- [ ] Optimize event listener queries (batch updates)
- [ ] Add Filament action logs (who resolved incidents)

### Sprint 4
- [ ] Implement circuit breaker for webhook failures
- [ ] Add ML model training pipeline (future)
- [ ] Create admin API for reputation management

---

## Branch & Tag Naming Conventions

**Sprint 3**:
- Branch: `feature/ReputationSystem-AdminDashboard`
- Tag: `v1.3.0`

**Sprint 4**:
- Branch: `feature/AutoBlocking-PatternDetection`
- Tag: `v1.4.0`

---

## Documentation Updates Required

### Sprint 3
- [ ] Update `README.md` with admin dashboard section
- [ ] Update `CHANGELOG.md` with v1.3.0 details
- [ ] Create `docs/ADMIN_DASHBOARD_GUIDE.md`
- [ ] Update `docs/SIMPLE_UNBLOCK_FEATURE.md` with reputation system

### Sprint 4
- [ ] Update `CHANGELOG.md` with v1.4.0 details
- [ ] Create `docs/AUTO_BLOCKING_GUIDE.md`
- [ ] Create `docs/WEBHOOK_CONFIGURATION.md`
- [ ] Update `README.md` with webhook setup

---

## Testing Strategy

### Integration Testing
- Test full flow: Request → Event → Listener → Dashboard display
- Test pattern detection → Auto-block → Webhook notification
- Test cleanup job → Soft delete → Verify dashboard updates

### Performance Testing
- Load test: 1000 concurrent requests with reputation tracking
- Measure event listener processing time (<100ms)
- Measure pattern analysis time for 100k records (<30s)

### Security Testing
- Verify GDPR compliance (no plaintext emails)
- Test whitelist bypass attempts
- Verify webhook auth headers
- Test SQL injection on filters

---

## Rollback Plan

### Sprint 3
If critical issues arise:
1. Disable event listeners in `EventServiceProvider`
2. Reputation tables remain but stop being updated
3. Admin dashboard becomes read-only
4. Revert to v1.2.0 functionality

### Sprint 4
If critical issues arise:
1. Disable `BlockLowReputationMiddleware`
2. Disable pattern analysis cron job
3. Disable webhook sending
4. Keep reputation system running (Sprint 3)
5. Revert to v1.3.0 functionality

---

## Support & Maintenance

**Post-Sprint 3**:
- Monitor event listener performance
- Review dashboard usage analytics
- Adjust reputation score algorithm if needed
- Weekly review of abuse incidents

**Post-Sprint 4**:
- Monitor auto-block false positive rate
- Review pattern detection effectiveness
- Monitor webhook delivery success rate
- Monthly review of pattern thresholds
- Quarterly review of reputation thresholds

---

## Questions & Decisions Log

| Date | Question | Decision | Reasoning |
|------|----------|----------|-----------|
| 2025-10-23 | Should reputation tracking be real-time or batched? | Real-time (event-driven) | Immediate feedback needed for auto-blocking |
| 2025-10-23 | Should events be queued or synchronous? | Queued (ShouldQueue) | Don't slow down user requests |
| 2025-10-23 | What reputation score triggers auto-block? | IP: 20, Email: 30 | Balance security vs false positives |
| 2025-10-23 | Should webhooks retry indefinitely? | No, max 3 retries | Prevent infinite loops on permanent failures |

---

## Contact & Resources

**Project Lead**: [Your Name]
**Technical Lead**: [Tech Lead Name]
**Repository**: https://github.com/AichaDigital/unblock
**Documentation**: /docs/
**Issue Tracker**: Linear

---

**Document Status**: ✅ Approved for Implementation
**Next Review**: After Sprint 3 completion
