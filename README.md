# Sistema de Cobranzas - Backend

Sistema de gestión de cobranzas desarrollado con CodeIgniter 4 que incluye un backend web para administración y una API REST para la aplicación móvil. Cuenta con arquitectura multitenant que permite gestionar múltiples organizaciones con completa separación de datos.

## Características

- **Arquitectura Multitenant**: Soporte para múltiples organizaciones con aislamiento de datos
- **Autenticación y Autorización**: Basada en roles (superadmin, admin, usuario)
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
   
   JWT_SECRET = 'your-secret-key'
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

- Cada organización es un tenant independiente con sus propios usuarios, clientes, carteras, facturas y pagos
- Los datos están segmentados por la columna `organization_id` en la mayoría de las tablas
- Los superadmins pueden cambiar entre organizaciones
- Los usuarios regulares solo ven datos de su organización asignada

### Componentes Clave para Multitenant

- **OrganizationFilter**: Middleware que procesa la selección de organización para superadmins
- **OrganizationTrait**: Trait que proporciona métodos consistentes para aplicar filtrado por organización
- **Auth::organizationId()**: Método que determina qué organización usar según el rol y contexto

## Configuración de la Aplicación Móvil

La aplicación móvil se comunica con el backend a través de una API REST. El sistema utiliza autenticación OTP (One-Time Password) para la autenticación de usuarios y JWT (JSON Web Tokens) para mantener las sesiones.

### Integración con Flutter

1. Configurar la URL base de la API en tu aplicación Flutter:
   ```dart
   final String baseUrl = 'http://tu-servidor.com/';
   ```

2. Flujo de autenticación:
   - Solicitar OTP: `POST /api/auth/otp/request`
   - Verificar OTP: `POST /api/auth/otp/verify`
   - Almacenar el token JWT para futuras solicitudes

3. Incluir el token JWT en todas las solicitudes a la API:
   ```dart
   final headers = {
     'Authorization': 'Bearer $jwtToken',
     'Content-Type': 'application/json',
   };
   ```

4. Implementar las principales funcionalidades:
   - Consulta de carteras y clientes asignados
   - Visualización de facturas pendientes
   - Registro de pagos (con captura de coordenadas GPS)
   - Historial de pagos registrados

Para más detalles, consultar la documentación de la API en formato OpenAPI en el archivo `openapi.yaml`.

## Webhooks

El sistema permite configurar webhooks para notificar a sistemas externos cuando ocurren eventos importantes.

### Configuración de Webhooks

1. Acceder a la sección Webhooks del sistema
2. Crear un nuevo webhook con los siguientes datos:
   - Nombre descriptivo
   - URL de destino
   - Eventos que activarán el webhook (payment.created, invoice.updated, etc.)
3. El sistema generará una clave secreta para firmar las notificaciones

### Seguridad de Webhooks

Las notificaciones incluyen una firma HMAC SHA256 en el encabezado `X-Webhook-Signature` para que el receptor pueda verificar la autenticidad de la solicitud.

Ejemplo de verificación en PHP:
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$secret = 'your-webhook-secret';

$calculatedSignature = hash_hmac('sha256', $payload, $secret);

if (hash_equals($calculatedSignature, $signature)) {
    // La solicitud es auténtica
    $data = json_decode($payload, true);
    // Procesar el evento
} else {
    // La solicitud podría ser falsificada
    http_response_code(401);
    echo json_encode(['error' => 'Signature verification failed']);
}
```

## Estructura del Proyecto

```
app/
├── Config/          # Archivos de configuración
├── Controllers/     # Controladores web y API
│   ├── Api/         # Controladores de la API REST
│   └── ...
├── Database/
│   ├── Migrations/  # Migraciones de la base de datos
│   └── Seeds/       # Seeders para datos iniciales
├── Filters/         # Filtros de autenticación y autorización 
│   ├── AuthFilter.php
│   ├── OrganizationFilter.php
│   └── ...
├── Libraries/       # Librerías personalizadas
│   ├── Auth.php
│   └── ...
├── Models/          # Modelos de datos
├── Traits/          # Traits reutilizables
│   ├── OrganizationTrait.php
│   └── ...
└── Views/           # Vistas del backend web
    ├── auth/        # Vistas de autenticación
    ├── clients/     # Vistas de gestión de clientes
    ├── invoices/    # Vistas de cuentas por cobrar
    ├── layouts/     # Plantillas y layouts
    ├── payments/    # Vistas de pagos
    ├── portfolios/  # Vistas de carteras
    ├── users/       # Vistas de usuarios
    └── webhooks/    # Vistas de webhooks
```

## Seguridad

- **Autenticación Web**: Basada en sesiones con protección CSRF
- **Autenticación API**: JWT con expiración configurable
- **Verificación OTP**: Códigos temporales para inicio de sesión seguro
- **Filtrado de Datos**: Separación estricta entre organizaciones
- **Validación de Entrada**: Prevención de inyección SQL y XSS
- **Encriptación**: Contraseñas con hash bcrypt
- **Firmas de Webhook**: Verificación HMAC SHA256

## Desarrollo

### Convenciones de Código

El proyecto sigue las convenciones PSR-12 para la codificación en PHP.

### Comandos Útiles

- Ejecutar servidor de desarrollo: `php spark serve`
- Crear una migración: `php spark migrate:create NombreMigracion`
- Ejecutar migraciones: `php spark migrate`
- Revertir migraciones: `php spark migrate:rollback`
- Crear un seeder: `php spark make:seeder NombreSeeder`
- Ejecutar un seeder: `php spark db:seed NombreSeeder`
- Limpiar caché: `php spark cache:clear`

### Solución de Problemas Comunes

- **Error CSRF**: Asegúrate de incluir el token CSRF en todos los formularios o usar las excepciones configuradas para rutas específicas
- **Problemas de Filtrado por Organización**: Verifica que estás usando el trait `OrganizationTrait` y que el contexto de sesión es correcto
- **Errores de SQLite**: Para desarrollo con SQLite, asegúrate de que la carpeta `writable/db` existe y tiene permisos de escritura

## Documentación

- **Análisis del Sistema**: Ver `ANALYSIS.md` para una descripción detallada de la arquitectura
- **API OpenAPI**: Ver `openapi.yaml` para la documentación completa de la API REST
- **Filtrado por Organización**: Ver `ORGANIZATION_FILTERING.md` para detalles sobre la implementación multitenant

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo LICENSE para más detalles.