# Guía de Despliegue en Render

## Configuración Inicial

### 1. Preparar el Repositorio

Asegúrate de que todos los archivos estén en tu repositorio:
- `Dockerfile`
- `scripts/00-laravel-deploy.sh`
- `scripts/01-setup.sh`
- `conf/nginx/nginx-site.conf`
- `.dockerignore`
- `.env.example`

### 2. Crear Cuenta en Pusher

1. Ve a [Pusher.com](https://pusher.com) y crea una cuenta
2. Crea una nueva aplicación
3. Anota las credenciales:
   - App ID
   - App Key
   - App Secret
   - Cluster

### 3. Configurar en Render

#### A. Crear Web Service

1. Ve a [Render.com](https://render.com) y crea una cuenta
2. Conecta tu repositorio de GitHub
3. Crea un nuevo "Web Service"
4. Selecciona tu repositorio
5. Configura:
   - **Name**: `kompapay-backend`
   - **Region**: Elige la más cercana
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Root Directory**: deja vacío
   - **Docker Command**: deja vacío (usará el Dockerfile)

#### B. Variables de Entorno

En la sección "Environment", añade estas variables:

```
APP_NAME=KompaPay
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-service-name.onrender.com
APP_KEY=base64:generate_this_with_artisan_key_generate

LOG_LEVEL=error
LOG_CHANNEL=stack

DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-db-name
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password

BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-app-key
PUSHER_APP_SECRET=your-pusher-app-secret
PUSHER_APP_CLUSTER=mt1
PUSHER_SCHEME=https
PUSHER_PORT=443

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME=KompaPay

SANCTUM_STATEFUL_DOMAINS=your-service-name.onrender.com
SESSION_DOMAIN=.onrender.com
```

#### C. Crear Base de Datos PostgreSQL

1. En Render, crea un nuevo "PostgreSQL" service
2. Anota las credenciales de conexión
3. Actualiza las variables de entorno con estos datos

#### D. Configurar Build & Deploy

1. **Build Command**: deja vacío (Docker maneja esto)
2. **Start Command**: deja vacío (Docker maneja esto)
3. **Auto-Deploy**: Habilitado

### 4. Generar APP_KEY

Después del primer despliegue (puede fallar), ve al shell de Render y ejecuta:

```bash
php artisan key:generate --show
```

Copia el resultado y actualiza la variable `APP_KEY` en Render.

### 5. Comandos Post-Despliegue

En el shell de Render, ejecuta:

```bash
# Migrar base de datos
php artisan migrate --force

# Crear usuario administrador (opcional)
php artisan tinker
>>> User::create(['name' => 'Admin', 'email' => 'admin@kompapay.com', 'password' => bcrypt('password123')]);
```

## URLs de la API

Una vez desplegado, tu API estará disponible en:

```
Base URL: https://your-service-name.onrender.com/api

Tableros:
GET    /api/tableros
POST   /api/tableros
GET    /api/tableros/{id}
PUT    /api/tableros/{id}
DELETE /api/tableros/{id}

Tareas:
GET    /api/tareas
POST   /api/tareas
GET    /api/tareas/{id}
PUT    /api/tareas/{id}
DELETE /api/tareas/{id}

Notas:
GET    /api/notas
POST   /api/notas
GET    /api/notas/{id}
PUT    /api/notas/{id}
DELETE /api/notas/{id}

WebSocket (Pusher):
Endpoint: wss://ws-{cluster}.pusher.com/app/{app_key}
```

## Configuración React Native

En tu app React Native, configura las URLs:

```javascript
// config.js
export const API_CONFIG = {
  baseURL: 'https://your-service-name.onrender.com/api',
  websocket: {
    key: 'your-pusher-app-key',
    cluster: 'mt1',
    forceTLS: true
  }
};
```

## Troubleshooting

### Error de Permisos
Si hay errores de permisos, verifica que el script `01-setup.sh` se ejecute correctamente.

### Error de Base de Datos
Verifica que las credenciales de PostgreSQL sean correctas y que la base de datos esté accesible.

### Error de Pusher
Verifica que las credenciales de Pusher sean correctas y que el cluster sea el correcto.

### Error 500
Revisa los logs en Render Dashboard > Service > Logs.

## Monitoreo

- **Logs**: Render Dashboard > Service > Logs
- **Métricas**: Render Dashboard > Service > Metrics
- **Shell**: Render Dashboard > Service > Shell

## Actualizaciones

Para actualizar la aplicación:
1. Haz push a tu repositorio
2. Render detectará automáticamente los cambios
3. Se ejecutará un nuevo build y despliegue
