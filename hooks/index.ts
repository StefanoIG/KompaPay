// Archivo índice para exportar todos los hooks de KompaPay
// Permite importar todos los hooks desde un solo lugar

// Configuración y tipos
export * from './config';
export * from './types';

// Hooks principales
export { useAPI } from './useAPI';
export { useAuth } from './useAuth';
export { useGroups, useGroup } from './useGroups';
export { useExpenses, useExpense, useGroupDebts } from './useExpenses';
export { useSync, useConflicts, useOffline } from './useSync';

// Hooks de utilidades
export { 
  useUtils, 
  useCache, 
  useAudit, 
  useNetworkStatus 
} from './useUtils';

// Re-exportar tipos importantes para fácil acceso
export type {
  User,
  Grupo,
  Gasto,
  SyncConflicto,
  AuthState,
  GroupsState,
  ExpensesState,
  SyncState,
  APIResponse,
  CreateUserRequest,
  LoginRequest,
  CreateGrupoRequest,
  CreateGastoRequest,
  DeudaResumen,
  PayDebtRequest,
} from './types';

// Re-exportar configuraciones importantes
export {
  BASE_URL,
  ENDPOINTS,
  STORAGE_KEYS,
  API_CONFIG,
  APP_CONFIG,
  EXPENSE_CATEGORIES,
  APIErrorType,
} from './config';

// Constantes útiles para la aplicación
export const HOOK_VERSION = '1.0.0';
export const SUPPORTED_FEATURES = [
  'authentication',
  'groups_management',
  'expenses_tracking',
  'debt_calculation',
  'offline_sync',
  'conflict_resolution',
  'audit_logging',
  'data_caching',
] as const;

// Configuraciones por defecto recomendadas
export const DEFAULT_PAGINATION = {
  page: 1,
  limit: 20,
} as const;

export const DEFAULT_CURRENCY = 'USD' as const;

// Métodos de utilidad para configuración rápida
export const createHookConfig = (options?: {
  baseUrl?: string;
  timeout?: number;
  currency?: string;
}) => ({
  baseUrl: options?.baseUrl || BASE_URL,
  timeout: options?.timeout || API_CONFIG.TIMEOUT,
  currency: options?.currency || DEFAULT_CURRENCY,
});

// Información del paquete
export const HOOK_INFO = {
  name: 'KompaPay React Native Hooks',
  version: HOOK_VERSION,
  description: 'Complete hook library for KompaPay expense management app',
  features: SUPPORTED_FEATURES,
  author: 'KompaPay Development Team',
  license: 'MIT',
} as const;
