// Configuración de la API para KompaPay

// Configuración general de la API
export const API_CONFIG = {
  // Base URL de la API - cambia según el entorno
  BASE_URL: process.env.NODE_ENV === 'development'
    ? 'http://localhost:8000/api'  // Desarrollo local
    : 'https://your-production-api.com/api', // Producción
  
  // Timeout para las peticiones (en milisegundos)
  TIMEOUT: 10000,
  
  // Headers por defecto
  DEFAULT_HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
};

// Claves para AsyncStorage
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'kompapay_auth_token',
  USER_DATA: 'kompapay_user_data',
  OFFLINE_DATA: 'kompapay_offline_data',
  OFFLINE_ACTIONS: 'kompapay_offline_actions',
  LAST_SYNC: 'kompapay_last_sync',
};

// URL base exportada para uso directo
export const BASE_URL = API_CONFIG.BASE_URL;

// Endpoints de la API
export const ENDPOINTS = {
  // Autenticación
  AUTH: {
    LOGIN: '/login',
    REGISTER: '/register',
    LOGOUT: '/logout',
    REFRESH: '/refresh',
    PROFILE: '/user',
    UPDATE_PROFILE: '/user',
  },
  
  // Usuarios
  USERS: {
    FIND_BY_PUBLIC_ID: '/users/find',
    MY_GROUPS: '/user/groups',
    MY_EXPENSES: '/user/expenses',
    MY_CONFLICTS: '/user/conflicts',
  },
  
  // Grupos
  GROUPS: {
    LIST: '/grupos',
    CREATE: '/grupos',
    SHOW: '/grupos',
    UPDATE: '/grupos',
    DELETE: '/grupos',
    JOIN: '/grupos/join',
    INVITE_MEMBER: '/grupos/{grupoId}/invitar',
    ADD_MEMBER: '/grupos/{grupoId}/members',
    REMOVE_MEMBER: '/grupos/{grupoId}/members/{usuarioId}',
    EXPENSES: '/grupos/{grupoId}/gastos',
  },
  
  // Gastos
  EXPENSES: {
    CREATE: '/gastos',
    SHOW: '/gastos',
    UPDATE: '/gastos',
    DELETE: '/gastos',
    MY_EXPENSES: '/user/gastos',
    GROUP_EXPENSES: '/grupos/{grupoId}/gastos',
    MY_DEBTS: '/user/deudas',
    GROUP_DEBTS: '/grupos/{grupoId}/deudas',
    PAY_DEBT: '/gastos/pay-debt',
    SEARCH: '/gastos/search',
    MARK_PAID: '/gastos/{id}/pagar',
    RESOLVE_CONFLICT: '/gastos/{id}/resolver',
    SYNC: '/gastos/sync',
    HISTORY: '/gastos/{gastoId}/history',
  },
  
  // Sincronización
  SYNC: {
    DATA: '/user/sync',
    UPDATE_TIME: '/user/sync/update',
    PUSH: '/sync/push',
    PULL: '/sync/pull',
    STATUS: '/sync/status',
    RESOLVE_CONFLICT: '/sync/conflicts',
  },
  
  // Auditoría
  AUDIT: {
    LOG: '/audit',
    USER_LOGS: '/user/audit',
    GROUP_LOGS: '/grupos/{grupoId}/audit',
  },
};

// Tipos de errores de la API
export enum APIErrorType {
  NETWORK_ERROR = 'NETWORK_ERROR',
  TIMEOUT_ERROR = 'TIMEOUT_ERROR',
  AUTH_ERROR = 'AUTH_ERROR',
  VALIDATION_ERROR = 'VALIDATION_ERROR',
  SERVER_ERROR = 'SERVER_ERROR',
  UNKNOWN_ERROR = 'UNKNOWN_ERROR',
}

// Categorías de gastos predefinidas
export const EXPENSE_CATEGORIES = [
  'Comida',
  'Transporte',
  'Entretenimiento',
  'Compras',
  'Servicios',
  'Viajes',
  'Salud',
  'Educación',
  'Hogar',
  'Otros',
];

// Configuración de la app
export const APP_CONFIG = {
  // Número máximo de reintentos para acciones offline
  MAX_OFFLINE_RETRIES: 3,
  
  // Intervalo de sincronización automática (en milisegundos)
  AUTO_SYNC_INTERVAL: 5 * 60 * 1000, // 5 minutos
  
  // Tiempo de cache para datos (en milisegundos)
  CACHE_DURATION: 15 * 60 * 1000, // 15 minutos
  
  // Límite de elementos por página
  PAGE_SIZE: 20,
  
  // Configuración de paginación
  PAGINATION: {
    INITIAL_PAGE: 1,
    PAGE_SIZE: 20,
    MAX_PAGES_IN_MEMORY: 5,
  },
};
