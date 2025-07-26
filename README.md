# KompaPay - Sistema de Colaboración Backend

Backend Laravel para el sistema de colaboración en tiempo real de KompaPay con tableros estilo Trello y notas colaborativas.

## 🏗️ Tecnologías

- **Laravel 11** - Framework PHP
- **Pusher** - WebSockets en tiempo real
- **MySQL/SQLite** - Base de datos
- **Sanctum** - Autenticación API
- **UUIDs** - Identificadores únicos

## 📊 Modelos Principales

### Grupos
- UUIDs como primary key
- Sistema de membresía de usuarios
- Autorización basada en pertenencia
- Gestión de permisos por grupo

### Tableros (Trello-style)
- UUIDs como primary key
- Ordenamiento automático
- Colores personalizables
- Pertenecen a grupos

### Tareas
- Estados: `pendiente`, `en_progreso`, `completada`
- Prioridades: `baja`, `media`, `alta`
- Asignación a usuarios del grupo
- Fechas de vencimiento
- Etiquetas JSON
- Drag & drop con ordenamiento

### Notas Colaborativas
- Edición simultánea con bloqueos
- Control de versiones automático
- Resolución de conflictos
- Indicadores de "usuario escribiendo"

## 🔧 Funcionalidades Implementadas

### Sistema de Grupos
- Creación y gestión de grupos
- Membresía de usuarios
- Autorización basada en pertenencia
- UUIDs públicos para identificación

### WebSockets en Tiempo Real
- Canales privados por grupo
- Eventos de creación/actualización/eliminación
- Sincronización automática entre clientes
- Presencia de usuarios online

### Autenticación y Autorización
- Sanctum para API tokens
- Políticas de acceso por recurso
- Middleware de autenticación obligatorio
- Validación de pertenencia a grupos

### Sistema de Bloqueos
- Bloqueo exclusivo para edición de notas
- Timeout automático de bloqueos
- Eventos WebSocket para bloqueos/desbloqueos

## 🎯 Reglas de Negocio

### Grupos
- Solo miembros pueden acceder a recursos del grupo
- UUIDs únicos para identificación pública
- Administradores pueden gestionar membresía

### Tableros
- Solo miembros del grupo pueden crear/ver tableros
- Ordenamiento automático al crear nuevos tableros
- No se puede eliminar tablero con tareas activas
- Colores personalizables por tablero

### Tareas
- Solo se pueden asignar a miembros del grupo
- Estado `completada` es final (no se puede revertir)
- Fechas de vencimiento opcionales
- Etiquetas personalizables por grupo
- Ordenamiento automático en creación

### Notas
- Bloqueo automático al iniciar edición
- Solo un usuario puede editar a la vez
- Versionado automático en cada actualización
- Resolución de conflictos con merge automático
- Timeout de bloqueo para prevenir bloqueos permanentes

### Seguridad
- Todos los endpoints requieren autenticación
- Validación de pertenencia a grupo en cada operación
- Logs de auditoría para cambios importantes
- Rate limiting en endpoints críticos
- Validación de entrada en todos los requests

## 🔄 Eventos WebSocket

### Grupos
- `grupo.updated` - Cambios en grupo
- `grupo.member_added` - Nuevo miembro
- `grupo.member_removed` - Miembro eliminado

### Tableros
- `tablero.created` - Nuevo tablero
- `tablero.updated` - Cambios en tablero
- `tablero.deleted` - Tablero eliminado

### Tareas
- `tarea.created` - Nueva tarea
- `tarea.updated` - Cambios en tarea
- `tarea.moved` - Tarea movida entre tableros
- `tarea.deleted` - Tarea eliminada

### Notas
- `nota.updated` - Contenido actualizado
- `nota.locked/unlocked` - Estados de bloqueo
- `user.typing` - Usuario escribiendo
- `user.stopped-typing` - Usuario dejó de escribir

## 🗄️ Base de Datos

### Características Técnicas
- UUIDs en todas las tablas principales
- Índices optimizados para consultas frecuentes
- Foreign keys con cascadas apropiadas
- Timestamps automáticos
- Soft deletes donde aplica

### Relaciones
- Grupos → Tableros (1:N)
- Grupos → Usuarios (N:N) mediante pivot
- Tableros → Tareas (1:N)
- Grupos → Notas (1:N)
- Users → Tareas (1:N) como asignado
- Users → Notas (1:N) como editor/bloqueador

## 🚀 Configuración

### Variables de Entorno
```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
BROADCAST_DRIVER=pusher
```

### Comandos de Setup
```bash
composer install
php artisan migrate
php artisan db:seed
```

## 🔐 Seguridad

- Autenticación Sanctum obligatoria
- Políticas de autorización por modelo
- Validación de entrada en todos los endpoints
- Canales WebSocket privados
- Rate limiting configurado
- Logs de auditoría implementados

## 📋 API Endpoints Principales

- `/api/grupos` - CRUD grupos
- `/api/grupos/{id}/tableros` - CRUD tableros
- `/api/tableros/{id}/tareas` - CRUD tareas
- `/api/grupos/{id}/notas` - CRUD notas
- `/api/notas/{id}/lock` - Sistema de bloqueos
- `/api/*/reorder` - Reordenamiento drag & drop

## 🧪 Testing

- Tests de feature para todos los CRUDs
- Tests de WebSocket events
- Tests de autorización y políticas
- Tests de integración Pusher
- Tests de reglas de negocio

## 📈 Rendimiento

- Eager loading en relaciones frecuentes
- Índices en campos de consulta
- Paginación en listados
- Cache de queries repetitivas
- Optimización de eventos WebSocket
- Queue jobs para operaciones pesadas
