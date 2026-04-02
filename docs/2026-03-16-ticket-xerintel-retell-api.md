# Propuesta: API REST para integración con Retell AI

- **Fecha:** 2026-03-16
- **Para:** Xerintel
- **De:** Abdelkarim Mateos (AichaDigital)
- **Referencia:** Integración Retell AI con desbloquear.xerintel.es

---

## Resumen

Para que Retell AI pueda consultar y gestionar bloqueos de firewall, necesito crear una API REST en desbloquear.xerintel.es. Esta API expone tres operaciones que el agente de voz de Retell podrá consumir como Custom Functions.

## Qué se construye

Una API con tres endpoints:

| Endpoint | Qué hace |
|----------|----------|
| **Consultar bloqueo** | Dado un dominio y una IP, responde si la IP está bloqueada, desde cuándo y por qué |
| **Desbloquear IP** | Dado un dominio, IP y email, ejecuta el proceso de desbloqueo y devuelve el resultado |
| **Bloquear IP** | Dado un dominio, IP y motivo, bloquea la IP en el firewall del servidor |

La API usa la misma lógica que ya funciona en el formulario web de desbloqueo. No es código nuevo experimental: es una puerta de acceso diferente al mismo motor.

## Qué necesito de vuestro lado

1. **Confirmar que tenéis cuenta activa en Retell AI** y acceso al dashboard para configurar Custom Functions
2. **Una vez entregada la API**, vuestro equipo de desarrollo debe configurar en Retell:
   - Las tres Custom Functions apuntando a los endpoints
   - El prompt del agente de voz para que pregunte al usuario por IP, dominio y email
   - Los headers de autenticación (os proporcionaré la API key)

La configuración de Retell AI no entra en este presupuesto — es responsabilidad de vuestro equipo. Entrego la API funcionando, documentada y testeada, junto con la especificación técnica exacta (URLs, parámetros, formatos de respuesta) para que la integración sea directa.

## Seguridad

- Autenticación por API key (os la proporciono de forma segura)
- Límite de peticiones (10/minuto por defecto, ajustable)
- Registro de todas las operaciones para auditoría
- Sin exposición de datos internos del servidor

## Plazo

Disponible para empezar de inmediato. Estimación de entrega: 1 semana desde confirmación.

## Propuesta económica

| Concepto | Detalle |
|----------|---------|
| **Tarifa** | 32,50 EUR/hora |
| **Estimación** | 10-16 horas |
| **Rango** | 325,00 - 520,00 EUR |
| **IVA** | No incluido |

El rango incluye: desarrollo de la API, middleware de seguridad, tests completos, despliegue en desbloquear.xerintel.es y documentación técnica de los endpoints para vuestro equipo.

**No incluye:**

- Configuración del agente de voz en Retell AI
- Soporte al equipo de desarrollo para la integración en Retell
- Auditoría de seguridad post-integración (se presupuesta por separado si se solicita)

Facturo las horas reales trabajadas dentro del rango. Como siempre, si surgen imprevistos que puedan superar el máximo, aviso antes de seguir.

---

*Abdelkarim Mateos — AichaDigital*
*abdelkarim@aichadigital.es*
