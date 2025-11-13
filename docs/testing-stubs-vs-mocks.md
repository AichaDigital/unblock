# Testing: Stubs vs Mocks - Guía de Refactorización

## Problema Identificado

Actualmente hay muchos tests que usan **mocks completos** (Mockery) cuando podrían usar **stubs** (datos de prueba reales), siguiendo el patrón ya establecido en el proyecto.

## Comparación: Antes vs Después

### ❌ ANTES: Usando Mocks (Complejo y Verboso)

```php
test('unblock action calls csf unblock and whitelist separately', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // 1. Check if IP is in permanent deny list (returns empty - not found)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'csf_deny_check',
            '1.2.3.4'
        )
        ->andReturn(''); // Not in permanent deny

    // 2. Check if IP is in temporary deny list (returns empty - not found)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'csf_tempip_check',
            '1.2.3.4'
        )
        ->andReturn(''); // Not in temporary deny

    // 3. Expect whitelist_simple command (always executed after checks)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'whitelist_simple',
            '1.2.3.4'
        )
        ->andReturn('Whitelist added');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('1.2.3.4', $this->host->id, 'test-key');

    expect($result['success'])->toBeTrue();
});
```

**Problemas:**
- 40+ líneas de código
- Mucha repetición de `shouldReceive()->once()->with()->andReturn()`
- No usa la lógica real de `FirewallService`
- Difícil de mantener cuando cambia la interfaz
- No detecta problemas reales de integración

### ✅ DESPUÉS: Usando Stubs (Simple y Realista)

```php
test('unblock action calls csf unblock and whitelist separately', function () {
    // ✅ MUCHO MÁS SIMPLE: Usar stub helper
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'Whitelist added');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('1.2.3.4', $this->host->id, 'test-key');

    expect($result['success'])->toBeTrue();
});
```

**Ventajas:**
- Solo 5 líneas de código
- Más legible y expresivo
- Usa la lógica real de `FirewallService`
- Más fácil de mantener
- Detecta problemas de integración reales

## Patrón Establecido en el Proyecto

Ya existe un patrón establecido en:
- `tests/Feature/Integration/RealFirewallAnalysisTest.php`
- `tests/Feature/Integration/DebugFirewallAnalysisTest.php`
- `tests/Unit/Services/Firewall/CpanelFirewallAnalyzerTest.php`

Estos tests usan **clases anónimas que extienden FirewallService** y retornan datos de stubs.

## Helper Creado: `FirewallServiceStub`

Se ha creado `tests/Helpers/FirewallServiceStub.php` que proporciona métodos helper:

### Métodos Disponibles

```php
// IP no bloqueada (caso más común)
FirewallServiceStub::ipNotBlocked()

// IP en deny permanente
FirewallServiceStub::ipInPermanentDeny('1.2.3.4')

// IP en deny temporal
FirewallServiceStub::ipInTemporaryDeny('1.2.3.4')

// IP en ambos denies
FirewallServiceStub::ipInBothDenies('1.2.3.4')

// Desde archivo stub existente
FirewallServiceStub::fromStubFile('tests/stubs/directadmin_deny_csf.php')

// Personalizado
(new FirewallServiceStub)
    ->setCommandResponse('csf_deny_check', '1.2.3.4')
    ->setCommandResponse('whitelist_simple', 'Success')
```

## Casos de Uso

### Caso 1: IP No Bloqueada (Más Común)

```php
// ❌ Con mocks: ~15 líneas
$firewallService = mock(FirewallService::class);
$firewallService->shouldReceive('checkProblems')
    ->with(..., 'csf_deny_check', ...)->andReturn('');
$firewallService->shouldReceive('checkProblems')
    ->with(..., 'csf_tempip_check', ...)->andReturn('');
// ... más mocks

// ✅ Con stubs: 1 línea
$firewallService = FirewallServiceStub::ipNotBlocked();
```

### Caso 2: IP en Deny Permanente

```php
// ❌ Con mocks: ~20 líneas de configuración

// ✅ Con stubs: 1 línea
$firewallService = FirewallServiceStub::ipInPermanentDeny('1.2.3.4');
```

### Caso 3: Usando Stubs Existentes

```php
// ✅ Cargar desde archivo stub existente
$firewallService = FirewallServiceStub::fromStubFile(
    'tests/stubs/directadmin_deny_csf.php'
);
```

## Tests que se Beneficiarían de esta Refactorización

### Alta Prioridad (Muchos Mocks)

1. ✅ `tests/Unit/Actions/UnblockIpActionWithWhitelistTest.php` - **6 tests con múltiples mocks**
2. ✅ `tests/Unit/Actions/UnblockIpActionNormalModeTest.php` - Varios tests con mocks
3. ✅ `tests/Feature/Actions/UnblockIpActionTest.php` - Tests con mocks repetitivos
4. ✅ `tests/Unit/Services/Firewall/V2/FirewallUnblockerTest.php` - Muchos mocks de FirewallService
5. ✅ `tests/Unit/Services/FirewallUnblockerTest.php` - Tests con mocks complejos

### Media Prioridad

6. Tests que mockean `SshSession` cuando podrían usar stubs
7. Tests que mockean múltiples comandos SSH cuando podrían usar stubs de archivos

## Ventajas de Usar Stubs

1. **Más Realista**: Usa la lógica real de `FirewallService`
2. **Menos Código**: Reduce significativamente el boilerplate
3. **Más Mantenible**: Cambios en `FirewallService` se reflejan automáticamente
4. **Mejor Detección de Bugs**: Detecta problemas de integración reales
5. **Más Legible**: El código de test es más expresivo
6. **Consistente**: Sigue el patrón ya establecido en el proyecto

## Desventajas de Mocks Excesivos

1. **Falsa Seguridad**: Los tests pasan pero el código real puede fallar
2. **Mantenimiento Costoso**: Cada cambio requiere actualizar múltiples mocks
3. **Difícil de Leer**: Mucho boilerplate oculta la intención del test
4. **No Detecta Problemas Reales**: Los mocks pueden no reflejar el comportamiento real

## Recomendación

**Refactorizar gradualmente** los tests que tienen muchos mocks para usar stubs, especialmente:

1. Tests de Actions que mockean `FirewallService`
2. Tests que tienen más de 3 `shouldReceive()` por test
3. Tests que repiten el mismo patrón de mocks múltiples veces

## Ejemplo Completo de Refactorización

Ver: `tests/Unit/Actions/UnblockIpActionWithWhitelistTest.refactored.php`

Este archivo muestra cómo se verían los tests refactorizados usando stubs en lugar de mocks.

## Próximos Pasos

1. ✅ Crear `FirewallServiceStub` helper
2. ⏳ Refactorizar `UnblockIpActionWithWhitelistTest.php` como ejemplo
3. ⏳ Refactorizar otros tests de Actions
4. ⏳ Crear helpers similares para otros servicios si es necesario
5. ⏳ Documentar el patrón en las reglas del proyecto

