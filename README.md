# Sistema de Cobranzas - Backend

Sistema de gestión de cobranzas desarrollado con CodeIgniter 4 que incluye un backend web para administración y una API REST para la aplicación móvil. Cuenta con arquitectura multitenant que permite gestionar múltiples organizaciones con completa separación de datos.

## Características

- **Arquitectura Multitenant**: Soporte para múltiples organizaciones con aislamiento de datos
- **Autenticación por OTP**: Inicio de sesión seguro mediante códigos de un solo uso enviados por SMS o email
- **Autorización**: Basada en roles (superadmin, admin, usuario)
- **Gestión de Organizaciones**: Administración completa de organizaciones independientes
- **Administración de Usuarios**: Diferentes niveles de acceso por rol
- **Gestión de Clientes**: Registro detallado de información de clientes
- **Carteras de Cobro**: Agrupación de clientes para asignación a cobradores
- **Control de Facturas**: Administración de cuentas por cobrar
- **Registro de Pagos**: Captura de pagos con geolocalización
- **Webhooks**: Integraciones con sistemas externos
- **API REST**: Comunicación con la aplicación móvil
- **Reportes y Dashboards**: Informes de gestión de cobranza

## Requisitos Técnicos

- PHP 7.4 o superior
- Base de datos compatible:
  - SQLite 3 (desarrollo/pruebas)
  - MySQL 5.7+ o MariaDB 10.3+ (producción)
  - PostgreSQL 9.6+ (producción)
- Servidor web compatible con PHP (Apache, Nginx)
- Extensiones PHP requeridas:
  - intl
  - json
  - mbstring
  - sqlite3 (para desarrollo)
  - mysql/mysqli (para producción con MySQL)
  - pgsql (para producción con PostgreSQL)
  - xml

## Instalación

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/tu-usuario/cobra-backend.git
   cd cobra-backend
   ```

2. Instalar dependencias con Composer:
   ```bash
   composer install
   ```

3. Configurar el entorno:
   ```bash
   cp env .env
   ```
   
4. Editar el archivo `.env` para configurar la base de datos y otros parámetros:
   ```
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'
   
   # Para SQLite (desarrollo)
   database.default.DBDriver = SQLite3
   database.default.database = ROOTPATH . 'writable/db/cobranzas.db'
   database.default.foreignKeys = true
   
   # Para MySQL/MariaDB (producción)
   # database.default.DBDriver = MySQLi
   # database.default.hostname = localhost
   # database.default.database = cobranzas
   # database.default.username = root
   # database.default.password = password
   
   # Configuración de Twilio para envío de SMS
   TWILIO_ACCOUNT_SID = 'your-account-sid'
   TWILIO_AUTH_TOKEN = 'your-auth-token'
   TWILIO_FROM_NUMBER = '+1234567890'
   ```

5. Crear la carpeta para la base de datos:
   ```bash
   mkdir -p writable/db
   ```

6. Ejecutar migraciones para crear la estructura de la base de datos:
   ```bash
   php spark migrate
   ```

7. Crear usuario superadmin para acceder al sistema:
   ```bash
   php spark db:seed SuperAdminSeeder
   ```
   
8. Iniciar el servidor de desarrollo:
   ```bash
   php spark serve
   ```

9. Acceder al sistema en `http://localhost:8080`
   - Usuario: `admin@admin.com`
   - Contraseña: `admin123`

## Arquitectura Multitenant

El sistema utiliza un enfoque de "tenant por organización" para implementar la arquitectura multitenant:

- Cada organización tiene su propio código único
- Los usuarios están asociados a una organización específica
- Los datos están completamente aislados entre organizaciones
- Los superadmins pueden gestionar todas las organizaciones

## Autenticación API

La API utiliza autenticación basada en tokens con el siguiente flujo:

1. **Solicitud de OTP**:
   ```http
   POST /api/auth/otp/request
   Content-Type: application/json

   {
     "phone": "+51999309748",
     "device_info": "iPhone 12, iOS 15.0"
   }
   ```

2. **Verificación de OTP**:
   ```http
   POST /api/auth/otp/verify
   Content-Type: application/json

   {
     "phone": "+51999309748",
     "code": "123456",
     "device_info": "iPhone 12, iOS 15.0"
   }
   ```

3. **Uso del Token**:
   ```http
   GET /api/clients
   Authorization: Bearer 23e0dd25f8536379e600271784e7dac460592bcb31ef73a0590d0330d1c9083d
   ```

Los tokens tienen una validez de 30 días y pueden ser utilizados para autenticar todas las solicitudes a la API.

## Webhooks

El sistema permite configurar webhooks para notificar a sistemas externos cuando ocurren eventos importantes.

### Configuración de Webhooks

1. Acceder a la sección Webhooks del sistema
2. Configurar la URL del endpoint que recibirá las notificaciones
3. Seleccionar los eventos que se desean notificar:
   - Nuevo pago registrado
   - Cliente actualizado
   - Factura pagada
   - etc.

### Formato de Notificaciones

Las notificaciones se envían como POST requests con el siguiente formato:

```json
{
  "event": "payment.created",
  "organization": "263274",
  "data": {
    "id": 123,
    "amount": 100.00,
    "currency": "PEN",
    "status": "completed",
    "created_at": "2025-03-26T15:45:30-05:00"
  }
}
```

## Contribución

1. Fork el repositorio
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## Licencia

Distribuido bajo la Licencia MIT. Ver `LICENSE` para más información.