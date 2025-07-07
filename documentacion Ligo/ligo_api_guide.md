# Guía de Integración - Ligo Payments API v1.7

## Información General

Ligo Payment es una plataforma segura y flexible diseñada para la administración de perfiles, empresas y auditoría de acciones. El sistema se enfoca en transferencias por CCE (Cámara de Compensación Electrónica) y recargas.

### Ambientes disponibles

- **Desarrollo**: `https://cce-auth-dev.ligocloud.tech` / `https://cce-api-gateway-dev.ligocloud.tech`
- **Producción**: `https://cce-auth-prod.ligocloud.tech` / `https://cce-api-gateway-prod.ligocloud.tech`

### Pre-requisitos técnicos

- **Tipo webservice**: REST
- **Métodos HTTP**: POST, GET
- **Parámetros de entrada**: Objeto JSON
- **Parámetros de salida**: Objeto JSON
- **Content-Type**: `application/json;charset=UTF-8`
- **Connection**: `keep-alive`

## Flujo de Autenticación

### 1. Inicio de Sesión (`/v1/auth/sign-in`)

El primer paso es obtener un token de acceso que será utilizado en todas las demás peticiones.

**Características importantes:**
- El token tiene una duración de **1 hora**
- Debe incluirse en el header `Authorization` de todas las peticiones posteriores
- Requiere un token firmado con llave privada en el header `Authorization`

**Ejemplo de uso:**

```bash
curl -X POST "https://cce-auth-dev.ligocloud.tech/v1/auth/sign-in?companyId=ef395d58-0582-42ec-b032-318a0ba6c0cc" \
  -H "Content-Type: application/json" \
  -H "Authorization: <token_firmado_con_llave_privada>" \
  -d '{
    "username": "jsoncco",
    "password": "Admin123"
  }'
```

**Respuesta exitosa:**
```json
{
  "status": 1,
  "errors": null,
  "code": 200,
  "data": {
    "userId": "7fabade9-c3a9-4a98-9383-33f04540c832",
    "companyId": "ef395d58-0582-42ec-b032-318a0ba6c0cc",
    "access_token": "eyJhbGciOiJSUzI1NiIXVCJ9..."
  },
  "date": "2024-09-23 09:08:40"
}
```

## Generación de Códigos QR

### 2. Crear QR (`/v1/createQr`)

Permite generar códigos QR estáticos o dinámicos para recibir pagos.

**Tipos de QR:**
- **11**: QR Estático (sin monto fijo)
- **12**: QR Dinámico (con monto específico)

**Ejemplo para QR estático:**

```bash
curl -X POST "https://cce-api-gateway-dev.ligocloud.tech/v1/createQr" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <access_token>" \
  -d '{
    "header": {
      "sisOrigen": "0921"
    },
    "data": {
      "qrTipo": "11",
      "idCuenta": "92100144571260631044",
      "moneda": "604",
      "importe": null,
      "fechaVencimiento": null,
      "cantidadPagos": null,
      "glosa": null,
      "codigoComerciante": "4829",
      "nombreComerciante": "BLADIMIR VASQUEZ RAMIREZ",
      "ciudadComerciante": "Lima",
      "info": null
    },
    "type": "TEXT"
  }'
```

### 3. Obtener información del QR (`/v1/getCreateQRById/{id}`)

Recupera la información completa del QR generado, incluyendo la cadena EMV.

```bash
curl -X GET "https://cce-api-gateway-dev.ligocloud.tech/v1/getCreateQRById/6dbe97c6-a4ad-48a2-a9fa-da6da08db321" \
  -H "Authorization: Bearer <access_token>"
```

**Respuesta típica:**
```json
{
  "status": 1,
  "code": 200,
  "data": {
    "header": {
      "codReturn": "0",
      "txtReturn": "SUCCESS"
    },
    "hash": "000201010211263700028001039030220241122092119919762965204482953036045802PE...",
    "idQr": "24112209211991976296",
    "errorMessage": null
  },
  "date": "2024-11-25 10:04:37"
}
```

## Webhook de Notificaciones

### 4. Webhook de Recargas (`/v1/send-recharge-notification`)

**⚠️ Importante**: Este es un webhook que **tu sistema debe implementar**. Ligo Payments enviará notificaciones a esta URL cuando se realicen recargas.

**Configuración requerida:**
- Implementar el endpoint en tu servidor
- Proporcionar la URL a Ligo Payments para configuración
- Responder con código 200 para confirmar recepción

**Ejemplo de implementación (Node.js/Express):**

```javascript
app.post('/v1/send-recharge-notification', (req, res) => {
  const notification = req.body;
  
  // Procesar la notificación
  console.log('Recarga recibida:', notification);
  
  // Validar y procesar la información
  const {
    instructionId,
    transferDetails,
    originDetails,
    destinationDetails,
    channel,
    rechargeDate,
    rechargeTime
  } = notification;
  
  // Tu lógica de negocio aquí...
  
  // Responder exitosamente
  res.status(200).json({
    status: true,
    code: 200,
    message: "Envío de notificación exitosa",
    date: new Date().toISOString()
  });
});
```

## Códigos de Referencia

### Tipos de Moneda
- **604**: Soles (PEN)
- **840**: Dólares (USD)

### Canales principales
- **15**: WEB
- **51**: Banca Móvil  
- **52**: NET
- **90**: Ventanilla
- **91**: Banca Móvil

### Tipos de Documento
- **1**: LE (Libreta Electoral)
- **2**: DNI (Documento Nacional de Identidad)
- **3**: LM (Libreta Militar)
- **4**: Pasaporte
- **5**: Carnet de Extranjería
- **6**: RUC (Registro Único de Contribuyentes)

### Tipos de Persona
- **N**: Natural
- **J**: Jurídico

## Manejo de Errores

### Códigos de respuesta comunes

- **200**: Operación exitosa
- **400**: Solicitud malformada
- **401**: Credenciales inválidas

### Estructura de error típica

```json
{
  "status": 0,
  "errors": {
    "message": "Credenciales inválidas",
    "code": 401
  },
  "code": 401,
  "date": "2024-09-20 17:51:54"
}
```

## Consideraciones de Seguridad

1. **Tokens de acceso**: Duran 1 hora, renovar antes del vencimiento
2. **HTTPS obligatorio**: Todas las comunicaciones deben ser por HTTPS
3. **Firma de tokens**: Se requiere firma con llave privada para autenticación inicial
4. **Validación de webhooks**: Implementar validación de origen en los webhooks

## Limitaciones y Buenas Prácticas

1. **Rate limiting**: Respetar los límites de tasa de la API
2. **Manejo de timeouts**: Implementar timeouts apropiados (recomendado: 30 segundos)
3. **Reintentos**: Implementar lógica de reintentos para fallos temporales
4. **Logging**: Registrar todas las transacciones para auditoría
5. **Monitoreo**: Monitorear el estado de los webhooks y APIs

## Casos de Uso Comunes

### Flujo típico para generar un QR de pago

1. Autenticarse con `/v1/auth/sign-in`
2. Crear QR con `/v1/createQr`
3. Obtener detalles del QR con `/v1/getCreateQRById/{id}`
4. Mostrar el código QR al usuario
5. Recibir notificación de pago via webhook `/v1/send-recharge-notification`

### Configuración de alertas de saldo

El manual también incluye un endpoint para configurar alertas cuando el saldo CCI alcance un umbral mínimo, aunque no fue incluido en los métodos solicitados específicamente.

## Soporte

Para más información o soporte técnico, consultar la documentación completa del manual o contactar al equipo de Ligo Payments.