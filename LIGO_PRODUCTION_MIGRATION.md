# 🚀 Guía de Migración a Producción - Ligo Payments

Esta guía te ayudará a migrar tu integración de Ligo desde el entorno de desarrollo a producción.

## 📋 Pre-requisitos

### 1. Credenciales de Producción de Ligo
Necesitas obtener de Ligo Payments:
- ✅ **Username de producción**
- ✅ **Password de producción** 
- ✅ **Company ID de producción**
- ✅ **Account ID de producción** (opcional, se usa por defecto si no se especifica)
- ✅ **Merchant Code de producción** (opcional, se usa por defecto si no se especifica)

### 2. Llaves RSA
Según Ligo: *"Generar un par de llaves RSA (privada/publica) y enviarnos la llave pública, la privada se queda con ustedes y la usaran para generar el token."*

## 🔧 Proceso de Migration

### Paso 1: Preparar la Base de Datos

```bash
# Ejecutar migración para agregar campos de producción
php spark migrate
```

### Paso 2: Generar Llaves RSA

```bash
# Generar par de llaves RSA
php spark ligo:credentials generate-key
```

Este comando creará:
- `writable/keys/ligo_private_key_YYYY-MM-DD_HH-mm-ss.pem` (mantener segura)
- `writable/keys/ligo_public_key_YYYY-MM-DD_HH-mm-ss.pem` (enviar a Ligo)

**⚠️ IMPORTANTE:** Envía la llave **pública** a Ligo Payments para registro.

### Paso 3: Configurar Credenciales de Producción

```bash
# Configurar credenciales interactivamente
php spark ligo:credentials set --org-id 1

# O configurar directamente con parámetros
php spark ligo:credentials set \
  --org-id 1 \
  --username "tu_username_prod" \
  --password "tu_password_prod" \
  --company-id "tu_company_id_prod" \
  --account-id "tu_account_id_prod" \
  --merchant-code "tu_merchant_code" \
  --private-key "writable/keys/ligo_private_key_YYYY-MM-DD_HH-mm-ss.pem" \
  --environment prod
```

### Paso 4: Migrar a Producción

```bash
# Migrar organización específica a producción
php spark ligo:migrate prod --org-id 1

# O migrar todas las organizaciones
php spark ligo:migrate prod

# Simular migración (dry-run)
php spark ligo:migrate prod --org-id 1 --dry-run
```

### Paso 5: Verificar Configuración

```bash
# Mostrar configuración actual
php spark ligo:credentials show --org-id 1

# Probar credenciales (validación local)
php spark ligo:credentials test --org-id 1
```

## 🔍 Verificaciones Post-Migración

### 1. Verificar Estado de la Organización

```bash
php spark ligo:credentials show --org-id 1
```

Deberías ver:
- ✅ Estado: Habilitado
- 🔴 Entorno: prod
- ✅ Todas las credenciales configuradas
- ✅ Llave privada RSA: Configurada
- URLs apuntando a prod: `https://cce-auth-prod.ligocloud.tech`

### 2. Probar Generación de QR

1. Ve a una factura en el sistema
2. Haz clic en "QR Ligo"
3. Verifica que se genere correctamente
4. Revisa los logs para confirmar uso de URLs de producción

### 3. Verificar Logs

```bash
tail -f writable/logs/log-$(date +%Y-%m-%d).php | grep -i ligo
```

Busca mensajes como:
- `🌍 ENTORNO: prod - Auth URL: https://cce-auth-prod.ligocloud.tech`
- `🚀 TOKEN CACHE HIT/MISS`
- `✅ QR generado exitosamente`

## 🛠️ Comandos Útiles

### Gestión de Credenciales

```bash
# Ver todas las organizaciones y su estado Ligo  
php spark ligo:credentials show

# Configurar credenciales para organización específica
php spark ligo:credentials set --org-name "Mi Empresa"

# Generar nuevas llaves RSA
php spark ligo:credentials generate-key

# Probar configuración
php spark ligo:credentials test --org-id 1
```

### Migraciónes de Entorno

```bash
# Migrar a producción
php spark ligo:migrate prod --org-id 1

# Volver a desarrollo (si es necesario)
php spark ligo:migrate dev --org-id 1

# Ver qué cambiaría sin aplicar
php spark ligo:migrate prod --org-id 1 --dry-run
```

## 🔒 Consideraciones de Seguridad

### Producción vs Desarrollo

| Aspecto | Desarrollo | Producción |
|---------|------------|------------|
| **SSL Verification** | ❌ Deshabilitado | ✅ Habilitado |
| **URLs** | `*-dev.ligocloud.tech` | `*-prod.ligocloud.tech` |
| **Logs** | Detallados | Reducidos |
| **Llaves RSA** | Prueba | Reales |

### Mejores Prácticas

1. **Llaves Privadas:**
   - Nunca commits al código
   - Almacenar con permisos restrictivos (600)
   - Hacer backup seguro

2. **Credenciales:**
   - Usar credenciales únicas por ambiente
   - Rotar periódicamente
   - No hardcodear en código

3. **Monitoreo:**
   - Verificar logs regularmente
   - Configurar alertas por errores
   - Monitorear expiración de tokens

## 🚨 Solución de Problemas

### Error: "Llave privada RSA inválida"

```bash
# Verificar formato de llave
openssl rsa -in tu_llave_privada.pem -check

# Regenerar si es necesario
php spark ligo:credentials generate-key
```

### Error: "Credenciales inválidas" 

1. Verificar que las credenciales son de producción
2. Confirmar que Ligo recibió tu llave pública
3. Verificar Company ID correcto

### Error: "SSL certificate problem"

- Verificar que `ligo_ssl_verify` esté en `true` para producción
- Confirmar que el servidor tiene certificados CA actualizados

### QR no se genera

1. Verificar logs:
   ```bash
   tail -f writable/logs/log-$(date +%Y-%m-%d).php | grep ERROR
   ```

2. Verificar conectividad:
   ```bash
   curl -I https://cce-api-gateway-prod.ligocloud.tech
   ```

3. Probar credenciales:
   ```bash
   php spark ligo:credentials test --org-id 1
   ```

## 📞 Soporte

### Información para Ligo Payments

Si necesitas contactar a Ligo, proporciona:

- **Company ID:** `[tu_company_id]`
- **Environment:** `prod`
- **Timestamp del error:** `[fecha y hora]`
- **Error específico:** `[mensaje de error]`

### Logs de Auditoría

Todos los cambios se registran en:
- **Base de datos:** `organizations` table
- **Logs de aplicación:** `writable/logs/`
- **Log específico:** Buscar `LIGO MIGRATION` y `LIGO CREDENTIALS`

---

## ✅ Checklist Final

- [ ] Migración de base de datos ejecutada
- [ ] Llaves RSA generadas
- [ ] Llave pública enviada a Ligo
- [ ] Credenciales de producción configuradas
- [ ] Organización migrada a entorno prod
- [ ] Verificación de configuración exitosa
- [ ] Prueba de generación de QR exitosa
- [ ] Logs verificados sin errores
- [ ] Backup de llaves privadas realizado

¡Una vez completado este checklist, tu integración con Ligo estará lista para producción! 🎉