# Documentación de Arquitectura - Sistema de Cobranzas

## Visión General

El Sistema de Cobranzas es una aplicación web/móvil multitenant desarrollada con CodeIgniter 4, diseñada para gestionar el proceso de cobranza de facturas para múltiples organizaciones con completa separación de datos.

El sistema implementa un patrón de arquitectura en capas con componentes claramente separados siguiendo principios MVC, centrado en la seguridad y escalabilidad.

## Arquitectura Técnica

### Patrón Arquitectónico

El sistema utiliza una arquitectura de N-capas con separación clara de responsabilidades:

1. **Capa de Presentación**: Interfaces de usuario web y móvil
2. **Capa de API**: Servicios RESTful para integración móvil y sistemas externos
3. **Capa de Aplicación**: Controladores, middleware y servicios
4. **Capa de Dominio**: Modelos y lógica de negocio 
5. **Capa de Persistencia**: Acceso a datos y almacenamiento

### Componentes Principales

#### Capa de Presentación
- **Backend Web**: Interfaz de administración basada en CodeIgniter 4 con vistas PHP
- **Aplicación Móvil**: Cliente Flutter que consume la API REST

#### Capa de API
- **API REST**: Endpoints JSON para integración con aplicación móvil
- **Servicios de Autenticación**: Sistema OTP para inicio de sesión móvil y JWT para mantenimiento de sesiones
- **Sistema de Webhooks**: Notificaciones a sistemas externos con firma HMAC SHA256

#### Capa de Aplicación
- **Gestión Multitenant**: Implementación basada en `OrganizationTrait` y filtrado por organización
- **Controladores**: Manejo de solicitudes HTTP y enrutamiento
- **Filtros y Middleware**: Autenticación, autorización y filtrado multitenant
- **Servicios**: Lógica de aplicación compartida y reutilizable

#### Capa de Dominio
- **Modelos**: Entidades del sistema y lógica de negocio asociada
- **Validación**: Reglas de validación y consistencia de datos
- **Lógica de Negocio**: Implementación de reglas específicas del dominio

#### Capa de Persistencia
- **Base de Datos**: Almacenamiento relacional con soporte para SQLite (desarrollo) y MySQL/PostgreSQL (producción)
- **Sistema de Caché**: Almacenamiento en caché con prefijos por organización

## Entidades Principales del Dominio

1. **Organizations**: Representan las empresas que utilizan el sistema (tenants)
2. **Users**: Usuarios del sistema con roles diferenciados (superadmin, admin, user)
3. **Clients**: Clientes asociados a una organización
4. **Portfolios**: Carteras de cobro que agrupan clientes para asignarlos a cobradores
5. **Invoices**: Facturas o cuentas por cobrar asociadas a clientes
6. **Payments**: Pagos realizados para saldar facturas
7. **Webhooks**: Configuraciones para notificar eventos a sistemas externos

## Modelo de Datos

### Relaciones Principales

- Una **organización** tiene muchos usuarios, clientes, carteras, facturas y webhooks
- Un **usuario** pertenece a una organización (excepto superadmin)
- Un **cliente** pertenece a una organización y puede estar en múltiples carteras
- Una **cartera** pertenece a una organización, contiene múltiples clientes y puede ser asignada a múltiples usuarios
- Una **factura** pertenece a un cliente y, por extensión, a una organización
- Un **pago** está asociado a una factura y es registrado por un usuario

## Implementación Multitenant

La arquitectura multitenant se implementa mediante el patrón "tenant por organización" con las siguientes características:

### Estrategia de Aislamiento

- **Aislamiento a nivel de fila**: Cada tabla que requiere segmentación por organización contiene una columna `organization_id`
- **Contexto de Organización**: 
  - Para superadmins: Selección dinámica de organización guardada en sesión
  - Para otros usuarios: Organización fija asignada al usuario

### Componentes Clave para Multitenant

1. **OrganizationTrait**: Proporciona métodos consistentes para aplicar filtrado:
   - `refreshOrganizationContext()`: Actualiza el contexto desde la sesión
   - `applyOrganizationFilter()`: Aplica el filtro a los modelos

2. **OrganizationFilter**: Middleware para manejar la selección de organización

3. **Auth::organizationId()**: Método que determina la organización contextual

## Seguridad

### Autenticación y Autorización

- **Web**: Autenticación basada en sesiones con protección CSRF
- **API**: JWT para gestión de sesiones, con OTP para la autenticación inicial
- **Verificación de Roles**: Control de acceso basado en roles (RBAC) en cada controlador

### Seguridad de Datos

- **Filtrado Multitenant**: Garantiza que cada tenant solo acceda a sus propios datos
- **Protección CSRF**: Para formularios web
- **Firmas de Webhook**: HMAC SHA256 para verificar la autenticidad de las notificaciones

### Protección de Contraseñas

- Almacenamiento con hash bcrypt
- Mecanismo de restablecimiento de contraseñas con tokens de un solo uso

## Integración y Extensibilidad

### API RESTful

- Endpoints completos para todas las entidades principales del sistema
- Documentación OpenAPI para facilitar la integración
- Versionado de API para compatibilidad futura

### Webhooks

- Notificaciones push para eventos como creación/actualización de pagos y facturas
- Firma de payload para verificación de autenticidad

## Consideraciones de Despliegue

### Entornos

- **Desarrollo**: SQLite, herramientas de depuración habilitadas
- **Pruebas**: Configuración similar a producción con datos de prueba
- **Producción**: MySQL/PostgreSQL, sin herramientas de depuración, optimización máxima

### Requisitos Técnicos

- PHP 7.4 o superior
- Base de datos relacional
- Extensiones PHP: intl, json, mbstring, etc.
- Servidor web compatible con PHP (Apache, Nginx)

## Escalabilidad y Rendimiento

### Optimizaciones Actuales

- Índices para columnas frecuentemente consultadas
- Queries optimizadas para reducir carga de base de datos
- Validación tanto en cliente como servidor para reducir tráfico

### Mejoras Planificadas

- Sistema de caché para consultas pesadas y reportes
- Optimización de JOINs múltiples con eager loading
- Implementación de pruebas automatizadas para garantizar rendimiento

## Restricciones y Limitaciones

- La aplicación móvil requiere conectividad a internet para funcionar
- Las importaciones masivas pueden afectar el rendimiento en momentos puntuales
- Dependencias específicas de la versión de CodeIgniter 4 utilizada

## Decisiones Arquitectónicas

| Decisión | Alternativas Consideradas | Justificación |
|----------|---------------------------|---------------|
| Arquitectura Multitenant con segregación a nivel de fila | Base de datos separadas por tenant | Mayor eficiencia en uso de recursos, más fácil mantenimiento |
| Autenticación OTP para la app móvil | Login tradicional con contraseña | Mayor seguridad al no almacenar contraseñas en dispositivos móviles |
| Implementación de webhooks | Polling periódico | Mejor rendimiento, menor carga en servidores, tiempo real |
| CodeIgniter 4 | Laravel, Symfony | Menor curva de aprendizaje, menor overhead, suficiente para requisitos actuales |

## Diagramas

### Diagrama de Arquitectura del Sistema

El siguiente diagrama muestra la arquitectura general del sistema, sus componentes principales y las interacciones entre ellos:

[Ver diagrama de arquitectura](architecture-diagram)

### Flujo de Autenticación Móvil

1. Usuario introduce email en la app
2. Backend genera código OTP y lo envía por correo/SMS
3. Usuario introduce el código OTP en la app
4. Backend verifica el código y genera token JWT
5. App almacena el token y lo usa en futuras solicitudes

### Flujo de Registro de Pagos

1. Cobrador selecciona factura pendiente en la app móvil
2. Introduce datos del pago (monto, método, referencia)
3. App captura coordenadas GPS automáticamente
4. Backend registra el pago y actualiza estado de factura
5. Sistema notifica mediante webhook a sistemas externos

## Planes Futuros

- Implementación de pruebas automatizadas
- Sistema de caché para mejorar rendimiento
- Optimización de consultas complejas
- Módulo de reportes avanzados
- Sincronización offline para la app móvil
