# KompaPay React Native Hooks

Esta carpeta contiene todos los hooks personalizados de TypeScript para la integración del frontend de React Native con el backend de KompaPay (Laravel).

## 📁 Estructura

```
hooks/
├── config.ts          # Configuración de API, endpoints y constantes
├── types.ts           # Interfaces y tipos TypeScript
├── useAPI.ts          # Hook base para peticiones HTTP
├── useAuth.ts         # Hook para autenticación y manejo de usuarios
├── useGroups.ts       # Hook para gestión de grupos
├── useExpenses.ts     # Hook para gestión de gastos y deudas
├── useSync.ts         # Hook para sincronización y resolución de conflictos
├── useUtils.ts        # Hooks de utilidades (formateo, caché, auditoría)
├── index.ts           # Archivo índice para exportaciones
└── README.md          # Esta documentación
```

## 🚀 Instalación y Uso

### 1. Instalar dependencias

Primero, instala las dependencias necesarias en tu proyecto React Native:

```bash
npm install @react-native-async-storage/async-storage
```

### 2. Copiar hooks

Copia toda la carpeta `hooks` a tu proyecto React Native:

```bash
cp -r hooks/ ./src/hooks/
```

### 3. Importar hooks

Importa los hooks que necesites desde el archivo índice:

```typescript
import { 
  useAuth, 
  useGroups, 
  useExpenses, 
  useSync,
  BASE_URL,
  ENDPOINTS 
} from './src/hooks';
```

## 📚 Documentación de Hooks

### useAuth - Autenticación

Hook principal para manejo de autenticación y perfil de usuario.

```typescript
const {
  user,
  token,
  isAuthenticated,
  loading,
  error,
  login,
  register,
  logout,
  updateProfile,
} = useAuth();

// Ejemplo de uso
const handleLogin = async () => {
  try {
    await login({
      email: 'usuario@example.com',
      password: 'password123'
    });
  } catch (error) {
    console.error('Error en login:', error);
  }
};
```

### useGroups - Gestión de Grupos

Hook para crear, gestionar y unirse a grupos de gastos.

```typescript
const {
  groups,
  currentGroup,
  loading,
  createGroup,
  joinGroup,
  addMember,
  removeMember,
  fetchGroups,
} = useGroups();

// Crear nuevo grupo
const newGroup = await createGroup({
  nombre: 'Viaje a la playa',
  descripcion: 'Gastos del viaje de verano',
});

// Unirse a grupo por ID público
await joinGroup({ id_publico: 'ABC123' });
```

### useExpenses - Gestión de Gastos

Hook para crear, actualizar y gestionar gastos dentro de grupos.

```typescript
const {
  myExpenses,
  groupExpenses,
  debts,
  loading,
  createExpense,
  updateExpense,
  fetchMyDebts,
  payDebt,
} = useExpenses();

// Crear nuevo gasto
const newExpense = await createExpense({
  descripcion: 'Cena en restaurante',
  monto: 120.50,
  categoria: 'Comida',
  grupo_id: 'grupo-uuid',
  participantes_ids: ['user1', 'user2', 'user3'],
});

// Pagar deuda
await payDebt({
  acreedor_id: 'user-uuid',
  grupo_id: 'grupo-uuid',
  monto: 40.17,
});
```

### useSync - Sincronización

Hook para sincronización de datos y resolución de conflictos.

```typescript
const {
  lastSync,
  pendingChanges,
  conflicts,
  performFullSync,
  resolveConflict,
  addOfflineAction,
} = useSync();

// Sincronización completa
const syncResult = await performFullSync();

// Resolver conflicto
await resolveConflict(conflictId, {
  action: 'keep_local' // o 'keep_remote', 'merge'
});
```

### useUtils - Utilidades

Hook con funciones de utilidad para formateo, validación y cálculos.

```typescript
const {
  formatCurrency,
  formatDate,
  validateExpenseData,
  calculateEqualSplit,
  isValidEmail,
} = useUtils();

// Formatear moneda
const formatted = formatCurrency(123.45); // "$123.45"

// Validar datos de gasto
const validation = validateExpenseData({
  descripcion: 'Almuerzo',
  monto: 25.50,
  categoria: 'Comida',
  participantes: ['user1', 'user2'],
});
```

## ⚙️ Configuración

### Configurar URL de la API

Edita el archivo `config.ts` para configurar la URL de tu API:

```typescript
export const API_CONFIG = {
  BASE_URL: process.env.NODE_ENV === 'development'
    ? 'http://localhost:8000/api'  // URL de desarrollo
    : 'https://tu-api-produccion.com/api', // URL de producción
  
  TIMEOUT: 10000, // 10 segundos
};
```

### Configurar AsyncStorage

Los hooks utilizan AsyncStorage para persistencia local. Asegúrate de que esté configurado en tu proyecto React Native.

## 🔧 Características Principales

### ✅ Funcionalidades Implementadas

- **Autenticación completa**: Login, registro, logout, actualización de perfil
- **Gestión de grupos**: Crear, unirse, invitar miembros, gestionar permisos
- **Gestión de gastos**: Crear, editar, eliminar, dividir gastos
- **Cálculo de deudas**: Automático entre miembros del grupo
- **Sincronización**: Manejo de datos offline y resolución de conflictos
- **Caché inteligente**: Para mejorar rendimiento y experiencia offline
- **Paginación**: Para listas largas de datos
- **Validación**: De datos antes de envío
- **Manejo de errores**: Completo y tipado
- **TypeScript**: Tipado completo para mejor DX

### 📱 Funcionalidades Offline

- **Acciones offline**: Se guardan y ejecutan cuando hay conexión
- **Caché de datos**: Para consultas rápidas sin conexión
- **Sincronización automática**: Al recuperar conexión
- **Resolución de conflictos**: Interface para resolver datos divergentes

### 🔄 Estados de Carga

Todos los hooks siguen un patrón consistente de estados:

```typescript
interface LoadingState {
  loading: boolean;
  error: string | null;
  success: boolean;
}
```

## 🎯 Endpoints Soportados

Los hooks cubren todos los 39 endpoints del backend:

### Autenticación (6 endpoints)
- POST `/login` - Iniciar sesión
- POST `/register` - Registrar usuario
- POST `/logout` - Cerrar sesión
- GET `/user` - Obtener perfil
- PUT `/user` - Actualizar perfil
- POST `/refresh` - Renovar token

### Grupos (9 endpoints)
- GET `/grupos` - Listar grupos
- POST `/grupos` - Crear grupo
- GET `/grupos/{id}` - Detalles del grupo
- PUT `/grupos/{id}` - Actualizar grupo
- DELETE `/grupos/{id}` - Eliminar grupo
- POST `/grupos/join` - Unirse a grupo
- POST `/grupos/{id}/invitar` - Invitar miembro
- POST `/grupos/{id}/members` - Agregar miembro
- DELETE `/grupos/{id}/members/{userId}` - Remover miembro

### Gastos (15 endpoints)
- GET `/user/gastos` - Mis gastos
- GET `/grupos/{id}/gastos` - Gastos del grupo
- POST `/gastos` - Crear gasto
- GET `/gastos/{id}` - Detalles del gasto
- PUT `/gastos/{id}` - Actualizar gasto
- DELETE `/gastos/{id}` - Eliminar gasto
- GET `/user/deudas` - Mis deudas
- GET `/grupos/{id}/deudas` - Deudas del grupo
- POST `/gastos/pay-debt` - Pagar deuda
- GET `/gastos/search` - Buscar gastos
- Y más...

### Sincronización (4 endpoints)
- GET `/user/sync` - Datos de sincronización
- POST `/sync/push` - Enviar cambios
- GET `/sync/status` - Estado de sincronización
- POST `/sync/conflicts` - Resolver conflictos

### Utilidades (5 endpoints)
- POST `/users/find` - Buscar usuario
- GET `/user/groups` - Mis grupos
- GET `/user/conflicts` - Mis conflictos
- GET `/audit` - Logs de auditoría
- Y más...

## 🛡️ Manejo de Errores

Los hooks incluyen manejo completo de errores:

```typescript
try {
  await createExpense(expenseData);
} catch (error) {
  if (error.type === 'VALIDATION_ERROR') {
    // Mostrar errores de validación
    console.log(error.details);
  } else if (error.type === 'NETWORK_ERROR') {
    // Manejar error de red
    console.log('Sin conexión');
  }
}
```

## 🧪 Testing

Para testear los hooks, puedes usar React Testing Library:

```typescript
import { renderHook, act } from '@testing-library/react-hooks';
import { useAuth } from './hooks';

test('should login successfully', async () => {
  const { result } = renderHook(() => useAuth());
  
  await act(async () => {
    await result.current.login({
      email: 'test@example.com',
      password: 'password',
    });
  });
  
  expect(result.current.isAuthenticated).toBe(true);
});
```

## 📄 Licencia

MIT License - Ver archivo LICENSE para más detalles.

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📞 Soporte

Para soporte o preguntas:
- Email: soporte@kompapay.com
- Documentación: https://docs.kompapay.com
- Issues: https://github.com/kompapay/react-native-hooks/issues

---

**Nota**: Estos hooks están diseñados específicamente para trabajar con el backend de KompaPay desarrollado en Laravel. Asegúrate de que tu backend esté corriendo y accesible antes de usar los hooks.
