# Documentación de Endpoints Ligo Payments

## 1. Envío de Consulta de Cuenta

**Descripción:**  
Consulta las cuentas CCI de los clientes externos.

**Endpoint:**  
`https://cce-api-gateway-{prefix}.ligocloud.tech/v1/accountInquiry`

**Método:**  
`POST`

### Header

| Parámetro     | Tipo         | Requerido | Descripción |
|---------------|--------------|-----------|-------------|
| Authorization | String(100)  | Sí        | Token firmado por el cliente utilizando la llave privada previamente enviada. |

### Body

| Parámetro               | Tipo          | Requerido | Descripción |
|-------------------------|---------------|-----------|-------------|
| debtorParticipantCode   | String(4)     | Sí  | Código de la Entidad Originante (`0XXX`). |
| creditorParticipantCode | String(4)     | Sí  | Código de la Entidad Receptora (`0XXX`). |
| debtorName              | String(140)   | No  | Nombre del Cliente Originante. |
| debtorId                | String(12)    | Sí  | N° Documento del Cliente Originante (`99999999` si mancomunada). |
| debtorIdCode            | String(1)     | Sí  | Tipo de Documento del Cliente Originante. |
| debtorPhoneNumber       | String(7)     | No  | Teléfono fijo del Cliente Originante. |
| debtorAddressLine       | String(70)    | No  | Dirección del Cliente Originante. |
| debtorMobileNumber      | String(100)   | No  | Celular del Cliente Originante. |
| transactionType         | String(3)     | Sí  | `320` Transferencia Ordinaria, `325` Pago a tarjeta. |
| channel                 | String(3)     | Sí  | Código del canal. |
| creditorAddressLine     | String(70)    | No  | Dirección del Cliente Receptor. |
| creditorPhoneNumber     | String(7)     | No  | Teléfono fijo del Cliente Receptor. |
| creditorMobileNumber    | String(9)     | No  | Celular del Cliente Receptor. |
| creditorCCI             | String(20)    | No  | CCI del Cliente Receptor (obligatorio salvo `325`). |
| creditorCreditCard      | String(20)    | No  | Tarjeta de crédito del Cliente Receptor (obligatorio si `325`). |
| debtorTypeOfPerson      | String(1)     | Sí  | Tipo de Persona. |
| currency                | String(3)     | Sí  | Código de Moneda. |
| proxyValue              | String(2048)  | No  | Reservado para MPP. |
| proxyType               | String(35)    | No  | Reservado para MPP. |

### Respuesta Exitosa

```json
{
  "status": 1,
  "errors": null,
  "code": 200,
  "data": {
    "msg": "Ok",
    "id": "0961a43d-1aa7-4bec-859a-5f1f6b300c67"
  },
  "date": "2024-09-20 17:51:54"
}
```

**Códigos de Respuesta:**
- `200` → Creación de orden de transferencia exitoso.
- `401` → Credenciales inválidas.

---

## 2. Obtener Información de Consulta de Cuenta

**Descripción:**  
Devuelve la información de una consulta de cuenta previamente enviada.

**Endpoint:**  
`https://cce-api-gateway-{prefix}.ligocloud.tech/v1/getAccountInquiryById/:id`

**Método:**  
`GET`

### Header

| Parámetro     | Tipo         | Requerido | Descripción |
|---------------|--------------|-----------|-------------|
| Authorization | String(100)  | Sí        | Token firmado por el cliente. |

### Params

| Parámetro | Tipo       | Requerido | Descripción |
|-----------|------------|-----------|-------------|
| id        | String(20) | Sí        | Código único generado en el envío de consulta de cuenta. |

### Respuesta Exitosa

```json
{
  "status": 1,
  "code": 200,
  "data": {
    "debtorParticipantCode": "0921",
    "creditorParticipantCode": "0049",
    "creationDate": "20241107",
    "creationTime": "165515",
    "terminalId": "A6515764",
    "retrievalReferenteNumber": "110716551575",
    "instructionId": "2024110716551509218115003277",
    "responseCode": "00",
    "creditorName": "HUAYLLANI PONCIANO MONTEROLA",
    "creditorAddressLine": "Jr Lima",
    "creditorId": "48175194",
    "creditorIdCode": "2",
    "creditorCCI": "04900100601812001010",
    "sameCustomerFlag": "O",
    "creditorParticipantName": "MI BANCO",
    "creditorCCICurrency": "604",
    "errorMessage": null
  },
  "date": "2024-09-20 17:51:54"
}
```

**Códigos de Respuesta:**
- `200` → Información obtenida exitosamente.
- `401` → Credenciales inválidas.

---

## 3. Obtener Código de Comisión

**Descripción:**  
Obtiene la comisión aplicable para una transferencia.

**Endpoint:**  
`https://cce-api-gateway-{prefix}.ligocloud.tech/v1/infoFeeCodeNew`

**Método:**  
`POST`

### Header

| Parámetro     | Tipo         | Requerido | Descripción |
|---------------|--------------|-----------|-------------|
| Authorization | String(100)  | Sí        | Token previamente generado. |

### Body

| Parámetro   | Tipo        | Requerido | Descripción |
|-------------|-------------|-----------|-------------|
| debtorCCI   | String(20)  | Sí  | CCI del Cliente Originante. |
| creditorCCI | String(20)  | Sí  | CCI del Cliente Receptor. |
| currency    | String(3)   | Sí  | Código de Moneda. |
| amount      | Number(9)   | Sí  | Importe a transferir (puede tener decimales `S/. 3.5`). |

### Respuesta Exitosa

```json
{
  "status": 1,
  "errors": null,
  "code": 200,
  "data": {
    "id": "5d97bc10-1762-4ecb-b2b5-b4fba5cb23bd",
    "placeType": "M",
    "rateCode": "5133",
    "fiatFeeAmount": 0,
    "feeLigo": 4,
    "feeTotal": 4
  },
  "date": "2024-11-07 16:56:59"
}
```

**Códigos de Respuesta:**
- `200` → Código de comisión obtenido.
- `401` → Credenciales inválidas.

---

## 4. Envío de Transferencia de Orden

**Descripción:**  
Realiza una transferencia a las cuentas CCI de las entidades externas.

**Endpoint:**  
`https://cce-api-gateway-{prefix}.ligocloud.tech/v1/orderTransferShipping`

**Método:**  
`POST`

### Header

| Parámetro     | Tipo         | Requerido | Descripción |
|---------------|--------------|-----------|-------------|
| Authorization | String(100)  | Sí        | Token firmado por el cliente utilizando la llave privada previamente enviada. |

### Body

| Parámetro               | Tipo          | Requerido | Descripción |
|-------------------------|---------------|-----------|-------------|
| debtorParticipantCode   | String(4)     | Sí  | Código de la Entidad Originante (`0XXX`). |
| creditorParticipantCode | String(4)     | Sí  | Código de la Entidad Receptora (`0XXX`). |
| messageTypeId           | String(4)     | Sí  | `0200` Requerimiento, `0201` Reenvío. |
| channel                 | String(3)     | Sí  | Código del canal. |
| amount                  | Number(12)    | Sí  | Importe (últimos 2 dígitos decimales). |
| currency                | String(3)     | Sí  | Código de Moneda. |
| referenceTransactionId  | String(35)    | Sí  | Código de referencia de la transferencia. |
| transactionType         | String(3)     | Sí  | `320` Ordinaria, `325` Pago a tarjeta. |
| feeAmount               | Number(15)    | Sí  | Comisión (últimos 2 dígitos decimales). |
| feeCode                 | String(4)     | Sí  | Código de Tarifa. |
| applicationCriteria     | String(1)     | Sí  | `M` Misma Plaza, `O` Otra Plaza, `E` Exclusiva. |
| debtorTypeOfPerson      | String(1)     | Sí  | Tipo de Persona. |
| debtorName              | String(100)   | Sí  | Nombre del Cliente Originante. |
| debtorAddressLine       | String(100)   | Sí  | Dirección del Cliente Originante. |
| debtorId                | String(12)    | Sí  | Documento del Cliente Originante. |
| debtorIdCode            | String(1)     | Sí  | Tipo de Documento. |
| debtorMobileNumber      | String(100)   | No  | Celular del Cliente Originante. |
| debtorCCI               | String(20)    | Sí  | CCI del Cliente Originante. |
| creditorName            | String(20)    | No  | Nombre del Cliente Receptor. |
| creditorAddressLine     | String(100)   | No  | Dirección del Cliente Receptor. |
| creditorCCI             | String(100)   | No  | CCI del Cliente Receptor. |
| sameCustomerFlag        | String(1)     | No  | `M` Mismo Cliente, `O` Otro Cliente. |
| purposeCode             | String(10)    | Sí  | Código de concepto de cobro. |
| unstructuredInformation | String(100)   | Sí  | Glosa de la transacción o idQr si QR CCE. |
| feeId                   | String(32)    | Sí  | Código único de la comisión TPP. |
| feeLigo                 | String(8)     | Sí  | Comisión TPP (últimos 2 dígitos decimales). |

### Respuesta Exitosa

```json
{
  "status": 1,
  "code": 200,
  "data": {
    "msg": "Ok",
    "id": "0961a43d-1aa7-4bec-859a-5f1f6b300c67"
  },
  "date": "2024-09-20 17:51:54"
}
```

**Códigos de Respuesta:**
- `200` → Envío exitoso.
- `401` → Credenciales inválidas.

---

## 5. Obtener Información de Transferencia de Orden

**Descripción:**  
Devuelve el detalle de una transferencia de orden previamente enviada.

**Endpoint:**  
`https://cce-api-gateway-{prefix}.ligocloud.tech/v1/getOrderTransferShippingById/:id`

**Método:**  
`GET`

### Header

| Parámetro     | Tipo         | Requerido | Descripción |
|---------------|--------------|-----------|-------------|
| Authorization | String(100)  | Sí        | Token firmado por el cliente. |

### Params

| Parámetro | Tipo       | Requerido | Descripción |
|-----------|------------|-----------|-------------|
| id        | String(20) | Sí        | Código único generado en el envío de transferencia de orden. |

### Respuesta Exitosa

```json
{
  "status": 1,
  "code": 200,
  "data": {
    "debtorParticipantCode": "0921",
    "creditorParticipantCode": "0049",
    "retrievalReferenteNumber": "110716574287",
    "trace": "000176",
    "transactionReference": "000000000176",
    "responseCode": "00",
    "feeAmount": 0,
    "settlementDate": "20241108",
    "transactionType": "320",
    "debtorCCI": "92100116665856271049",
    "creditorCCI": "04900100601812001010",
    "sameCustomerFlag": "M",
    "instructionId": "20241108120000987654321",
    "creationDate": "20241108",
    "creationTime": "120000",
    "channel": "15",
    "interbankSettlementAmount": 10000,
    "errorMessage": null
  },
  "date": "2024-11-08 12:05:00"
}
```

**Códigos de Respuesta:**
- `200` → Información obtenida exitosamente.
- `401` → Credenciales inválidas.
