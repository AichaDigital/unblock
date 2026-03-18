# ADR: API REST para integración con Retell AI

- **Fecha:** 2026-03-16
- **Estado:** Propuesto
- **Autor:** Abdelkarim Mateos
- **Cliente:** Xerintel (desbloquear.xerintel.es)
- **Despliegue afectado:** central (simple mode)

---

## Contexto

Xerintel solicita integrar Retell AI (plataforma de agentes de voz con IA) con el sistema de desbloqueo. El objetivo es que un agente de voz pueda:

1. Consultar si una IP está bloqueada en un servidor
2. Solicitar el desbloqueo de forma automatizada
3. Consultar el estado de una solicitud anterior

Retell AI se integra con sistemas externos mediante **Custom Functions**: el agente de voz hace un `POST` a un endpoint externo, recibe JSON, y usa la respuesta para continuar la conversación.

## Decisión

Exponer una API REST síncrona en `desbloquear.xerintel.es` con tres endpoints, protegida por API key + verificación de firma Retell. La API reutiliza la lógica existente del Simple Mode sin duplicar código.

## Alternativas consideradas

| Opción | Pros | Contras | Decisión |
|--------|------|---------|----------|
| **API síncrona (elegida)** | Simple, un solo request-response, Retell soporta hasta 2min timeout | El proceso SSH puede tardar 10-30s | Aceptable: el job tarda <30s normalmente |
| API asíncrona con polling | Más robusto ante timeouts | Requiere 2 custom functions en Retell, más complejidad | Descartada: overengineering para el caso de uso |
| Webhook inverso (push) | Retell recibe cuando termina | Retell no soporta webhooks entrantes en custom functions | No viable |

## Arquitectura

### Endpoints

```
POST /api/v1/firewall/check      → Consultar estado de bloqueo de una IP
POST /api/v1/firewall/unblock    → Solicitar desbloqueo
POST /api/v1/firewall/block      → Solicitar bloqueo (uso admin/interno)
```

**Nota:** Se usa `/api/v1/firewall/` como namespace genérico, no acoplado a Retell. Cualquier sistema externo autenticado puede consumir esta API.

### Autenticación

Doble capa:

1. **API Key** — Header `Authorization: Bearer <token>`. Token almacenado en `.env` como `UNBLOCK_API_KEY`. Se valida en middleware.
2. **Firma Retell (opcional)** — Header `X-Retell-Signature`. Verificación con el SDK de Retell usando `RETELL_API_SECRET`. Se activa si la env var está configurada.
3. **Whitelist IP (opcional)** — IP fija de Retell: `100.20.5.228`. Configurable pero no obligatoria (limita flexibilidad en desarrollo).

### Request/Response

#### `POST /api/v1/firewall/check`

```json
// Request
{
    "ip": "1.2.3.4",
    "domain": "ejemplo.com"
}

// Response 200
{
    "status": "blocked",         // "blocked" | "not_blocked" | "unknown"
    "ip": "1.2.3.4",
    "domain": "ejemplo.com",
    "host": "srv1.ejemplo.com",
    "blocked_since": "2026-03-16T10:30:00Z",
    "block_reason": "lfd: excessive connections",
    "message": "La IP 1.2.3.4 está bloqueada en el firewall del servidor srv1.ejemplo.com"
}
```

#### `POST /api/v1/firewall/unblock`

```json
// Request
{
    "ip": "1.2.3.4",
    "domain": "ejemplo.com",
    "email": "usuario@ejemplo.com"
}

// Response 200
{
    "status": "unblocked",       // "unblocked" | "not_blocked" | "failed" | "denied"
    "ip": "1.2.3.4",
    "domain": "ejemplo.com",
    "was_blocked": true,
    "report_id": "uuid-del-report",
    "message": "La IP 1.2.3.4 ha sido desbloqueada correctamente en srv1.ejemplo.com"
}
```

#### `POST /api/v1/firewall/block`

```json
// Request
{
    "ip": "1.2.3.4",
    "domain": "ejemplo.com",
    "reason": "Motivo del bloqueo"
}

// Response 200
{
    "status": "blocked",
    "ip": "1.2.3.4",
    "domain": "ejemplo.com",
    "message": "La IP 1.2.3.4 ha sido bloqueada en srv1.ejemplo.com"
}
```

#### Errores comunes

```json
// 401 - No autenticado
{ "error": "unauthorized", "message": "API key inválida o ausente" }

// 404 - Dominio no encontrado
{ "error": "domain_not_found", "message": "El dominio ejemplo.com no está registrado en el sistema" }

// 422 - Validación
{ "error": "validation_error", "message": "...", "errors": { "ip": ["..."] } }

// 429 - Rate limit
{ "error": "rate_limited", "message": "Demasiadas solicitudes. Intente de nuevo en X segundos" }
```

### Componentes a crear

| Componente | Tipo | Descripción |
|------------|------|-------------|
| `FirewallApiController` | Controller | 3 métodos: check, unblock, block |
| `AuthenticateApiKey` | Middleware | Valida `Authorization: Bearer` contra `UNBLOCK_API_KEY` |
| `VerifyRetellSignature` | Middleware (opcional) | Valida `X-Retell-Signature` si `RETELL_API_SECRET` está configurado |
| `CheckIpBlockStatusAction` | Action | Consulta CSF para verificar si IP está bloqueada (nueva) |
| `BlockIpAction` | Action | Bloquea IP en CSF (nueva o reutilizada) |
| `CheckFirewallRequest` | FormRequest | Validación para check |
| `UnblockFirewallRequest` | FormRequest | Validación para unblock |
| `BlockFirewallRequest` | FormRequest | Validación para block |

### Reutilización de lógica existente

El endpoint `/unblock` reutiliza directamente:

- `ValidateIpFormatAction` — Validar formato IP
- `ValidateDomainInDatabaseAction` — Buscar dominio en DB
- `AnalyzeFirewallForIpAction` — Consultar CSF
- `EvaluateUnblockMatchAction` — Decidir si desbloquear
- `UnblockIpAction` — Ejecutar desbloqueo
- `CreateSimpleUnblockReportAction` — Crear report

La diferencia con el flujo web: **no hay OTP, no hay usuario temporal, no hay job asíncrono**. La API ejecuta el pipeline de forma síncrona y devuelve el resultado.

### Configuración

```php
// config/unblock.php
'api' => [
    'enabled' => env('UNBLOCK_API_ENABLED', false),
    'key' => env('UNBLOCK_API_KEY'),
    'retell_secret' => env('RETELL_API_SECRET'),
    'rate_limit_per_minute' => env('UNBLOCK_API_RATE_LIMIT', 10),
    'allowed_ips' => env('UNBLOCK_API_ALLOWED_IPS'), // CSV, opcional
],
```

```env
# .env (solo en central/Xerintel)
UNBLOCK_API_ENABLED=true
UNBLOCK_API_KEY=ulk_xrt_...
RETELL_API_SECRET=ret_...     # opcional, si usan firma
UNBLOCK_API_RATE_LIMIT=10
```

### Rate Limiting

- 10 requests/minuto por API key (configurable)
- Se aplica a nivel global de la API, no por endpoint
- Independiente del rate limiting del Simple Mode web

### Rutas

```php
// routes/api.php
Route::prefix('v1/firewall')
    ->middleware(['api.key', 'throttle:api_firewall'])
    ->group(function () {
        Route::post('/check', [FirewallApiController::class, 'check']);
        Route::post('/unblock', [FirewallApiController::class, 'unblock']);
        Route::post('/block', [FirewallApiController::class, 'block']);
    });
```

### Seguridad

- **Sin CORS abierto** — La API no se consume desde navegador, no necesita CORS
- **Logging** — Cada request se registra en `activity_log` con IP origen, acción y resultado
- **No expone datos sensibles** — Las respuestas no incluyen credenciales SSH, rutas de servidor ni detalles internos
- **Validación estricta** — IPs validadas como IPv4/IPv6, dominios contra regex + existencia en DB
- **API key rotable** — Cambiar en `.env` y reiniciar, sin deploy

### Configuración en Retell AI (responsabilidad de Xerintel)

Xerintel/su equipo debe configurar en el dashboard de Retell:

1. **Custom Function `check_ip_block`:**
   - Method: POST
   - URL: `https://desbloquear.xerintel.es/api/v1/firewall/check`
   - Headers: `Authorization: Bearer <API_KEY>`
   - Parameters: `ip` (string, required), `domain` (string, required)

2. **Custom Function `unblock_ip`:**
   - Method: POST
   - URL: `https://desbloquear.xerintel.es/api/v1/firewall/unblock`
   - Headers: `Authorization: Bearer <API_KEY>`
   - Parameters: `ip` (string, required), `domain` (string, required), `email` (string, required)

3. **Prompt del agente de voz** con instrucciones para recopilar IP, dominio y email antes de llamar a las funciones.

## Testing

- Tests unitarios para cada Action nueva
- Tests feature para cada endpoint (200, 401, 404, 422, 429)
- Tests con `UNBLOCK_API_ENABLED=false` verificando que retorna 404
- Tests de middleware de autenticación
- Tests de rate limiting
- Cobertura en ambos modos (la API funciona independientemente de `simple_mode.enabled`)

## Estimación

| Tarea | Horas |
|-------|-------|
| Controller + FormRequests + rutas | 2-3 |
| Middleware auth (API key + Retell signature) | 1-2 |
| Actions nuevas (check status, block) | 2-3 |
| Integración con Actions existentes (unblock pipeline) | 1-2 |
| Tests completos (endpoints + middleware + rate limit) | 3-4 |
| Config + env + deploy en central | 1-2 |
| **Total** | **10-16** |

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Timeout SSH >30s en análisis de firewall | Retell tiene 2min timeout + 2 retries. Suficiente |
| API key comprometida | Rotación inmediata en `.env`, whitelist IP opcional |
| Abuso del endpoint de block | Rate limiting + logging + API key exclusiva |
| Equipo externo (India) mal configura Retell | No es nuestro scope. La API responde correctamente; la configuración de Retell es responsabilidad de Xerintel |
