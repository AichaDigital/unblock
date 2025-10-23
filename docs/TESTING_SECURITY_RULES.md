# REGLAS DE ORO PARA TESTS 🔥

## ⚠️ NUNCA, JAMÁS, BAJO NINGUNA CIRCUNSTANCIA:

1. **NO uses passwords reales** en tests
2. **NO uses API keys reales** en tests  
3. **NO uses tokens reales** en tests
4. **NO uses credenciales de servicios reales** en tests
5. **NO uses datos de tarjetas de crédito reales** en tests

## ✅ LO QUE SÍ PUEDES HACER:

1. **Usa passwords de prueba** con prefijo "Test": `TestP@ssw0rd!123456`
2. **Usa API keys fake** claramente marcadas: `test_api_key_fake_abc123`
3. **Usa tokens fake**: `test_token_1234567890abcdef`
4. **Marca todo con comentarios**: `// ggignore - Test data, not a real secret`

## 🛡️ PROTECCIÓN:

- GitGuardian **SÍ escanea los tests** para detectar secretos reales
- Solo ignora passwords específicos documentados en `.gitguardian.yaml`
- Si accidentalmente pones un secreto real, GitGuardian lo detectará

## 📝 EJEMPLOS CORRECTOS:

```php
// ✅ CORRECTO - Claramente marcado como test
$testPassword = 'TestP@ssw0rd!123456'; // ggignore

// ✅ CORRECTO - API key fake
$fakeApiKey = 'test_stripe_key_fake_123abc'; // ggignore

// ✅ CORRECTO - Token fake
$testToken = 'test_jwt_token_not_real_xyz789'; // ggignore
```

## ❌ EJEMPLOS INCORRECTOS:

```php
// ❌ INCORRECTO - Password que parece real
$password = 'MyR3alP@ssw0rd!2024';

// ❌ INCORRECTO - API key real
$apiKey = 'sk_live_51H7x8h2eZvKYlo2C...'; // GitGuardian lo detectará!

// ❌ INCORRECTO - Token real
$token = 'ghp_1234567890abcdefghijklmnopqrst'; // GitGuardian lo detectará!
```

## 🔥 RECUERDA:

> "Si parece real, GitGuardian lo tratará como real"
> "Usa prefijo 'Test' o 'Fake' en todo dato sensible de tests"
> "Cada dato fake debe tener comentario // ggignore"

---

**Grabado a fuego en la memoria del proyecto** 🔥

