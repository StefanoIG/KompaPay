// Tipos TypeScript para KompaPay API

// Tipos base
export interface APIResponse<T = any> {
  success: boolean;
  message: string;
  data?: T;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> extends APIResponse<T[]> {
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Usuario
export interface User {
  id: string;
  nombre: string;
  email: string;
  email_verified_at?: string;
  id_publico: string;
  ultima_sync?: string;
  created_at: string;
  updated_at: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  nombre: string;
  email: string;
  password: string;
}

export interface UpdateUserRequest {
  nombre?: string;
  email?: string;
  password?: string;
}

export interface LoginResponse {
  user: User;
  token: string;
}

// Grupo
export interface Grupo {
  id: string;
  nombre: string;
  creado_por: string;
  id_publico: string;
  fecha_creacion: string;
  created_at: string;
  updated_at: string;
  // Relaciones
  creador?: User;
  miembros?: User[];
  gastos?: Gasto[];
}

export interface CreateGrupoRequest {
  nombre: string;
  descripcion?: string;
}

export interface UpdateGrupoRequest {
  nombre?: string;
  descripcion?: string;
}

export interface JoinGroupRequest {
  id_publico: string;
}

export interface InviteMemberRequest {
  email: string;
  mensaje?: string;
}

// Gasto
export interface Gasto {
  id: string;
  grupo_id: string;
  descripcion: string;
  monto: number;
  tipo_division: 'equitativa' | 'porcentaje' | 'personalizada';
  pagado_por: string;
  modificado_por?: string;
  id_publico: string;
  nota?: string;
  fecha_creacion: string;
  ultima_modificacion: string;
  created_at: string;
  updated_at: string;
  // Relaciones
  grupo?: Grupo;
  pagador?: User;
  modificador?: User;
  participantes?: GastoParticipante[];
  conflictos?: SyncConflicto[];
  auditLogs?: AuditLog[];
}

export interface GastoParticipante extends User {
  pivot: {
    id: string;
    gasto_id: string;
    user_id: string;
    monto_proporcional: number;
    pagado: boolean;
    fecha_pago?: string;
    created_at: string;
    updated_at: string;
  };
}

export interface CreateGastoRequest {
  grupo_id: string;
  descripcion: string;
  monto: number;
  tipo_division?: 'equitativa' | 'porcentaje' | 'personalizada';
  participantes: {
    user_id: string;
    monto_proporcional: number;
  }[];
  nota?: string;
}

export interface UpdateGastoRequest {
  descripcion?: string;
  monto?: number;
  tipo_division?: 'equitativa' | 'porcentaje' | 'personalizada';
  participantes?: {
    user_id: string;
    monto_proporcional: number;
  }[];
  nota?: string;
}

export interface MarkPaidRequest {
  user_id: string;
  confirmado: boolean;
  nota?: string;
}

// Sincronización
export interface SyncData {
  user: User;
  grupos: Grupo[];
  gastos: Gasto[];
  conflictos: SyncConflicto[];
  timestamp_sync: string;
}

export interface SyncConflicto {
  id: string;
  gasto_id: string;
  version_a: Record<string, any>;
  version_b: Record<string, any>;
  creado_por: string;
  resuelto: boolean;
  aprobado_por_creador: boolean;
  aprobado_por_otro: boolean;
  fecha_conflicto: string;
  resuelto_el?: string;
  created_at: string;
  updated_at: string;
  // Relaciones
  gasto?: Gasto;
  creador?: User;
}

export interface ResolveConflictRequest {
  resolucion: 'version_a' | 'version_b' | 'manual';
  datos_manuales?: Record<string, any>;
  comentario?: string;
}

// Audit Log
export interface AuditLog {
  id: string;
  gasto_id: string;
  accion: string;
  detalle: Record<string, any>;
  hecho_por: string;
  timestamp: string;
  created_at: string;
  updated_at: string;
  // Relaciones
  gasto?: Gasto;
  usuario?: User;
}

// Búsqueda de usuario
export interface FindUserRequest {
  id_publico: string;
}

export interface UserSearchResult {
  id: string;
  nombre: string;
  id_publico: string;
}

// Estados de carga
export interface LoadingState {
  loading: boolean;
  error: string | null;
  success: boolean;
}

// Estados específicos para hooks
export interface AuthState extends LoadingState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
}

export interface GroupsState extends LoadingState {
  groups: Grupo[];
  currentGroup: Grupo | null;
}

export interface ExpensesState extends LoadingState {
  expenses: Gasto[];
  myExpenses: Gasto[];
  groupExpenses: Gasto[];
  currentExpense: Gasto | null;
  debts: DeudaResumen[];
  hasMore: boolean;
  page: number;
}

export interface DeudaResumen {
  acreedor_id: string;
  acreedor_nombre: string;
  acreedor_email: string;
  deudor_id: string;
  deudor_nombre: string;
  deudor_email: string;
  grupo_id: string;
  grupo_nombre: string;
  total_deuda: number;
  created_at: string;
  updated_at: string;
}

export interface PayDebtRequest {
  acreedor_id: string;
  grupo_id: string;
  monto: number;
  descripcion?: string;
}

export interface GroupExpensesParams {
  desde?: string;
  hasta?: string;
  categoria?: string;
  pagador_id?: string;
  participante_id?: string;
}

export interface SyncData {
  grupos: Grupo[];
  gastos: Gasto[];
  usuarios: User[];
  last_sync: string;
  changes_count: number;
}

export interface ResolveSyncConflictRequest {
  action: 'keep_local' | 'keep_remote' | 'merge';
  merged_data?: any;
}

export interface SyncState extends LoadingState {
  lastSync: string | null;
  pendingChanges: number;
  conflicts: SyncConflicto[];
}

// Tipos para navegación (React Navigation)
export type RootStackParamList = {
  Login: undefined;
  Register: undefined;
  Home: undefined;
  Groups: undefined;
  GroupDetail: { groupId: string };
  CreateGroup: undefined;
  Expenses: { groupId: string };
  ExpenseDetail: { expenseId: string };
  CreateExpense: { groupId: string };
  Profile: undefined;
  Sync: undefined;
  Conflicts: undefined;
};

// Eventos de la aplicación
export interface AppEvent {
  type: string;
  payload?: any;
  timestamp: number;
}

// Configuración offline
export interface OfflineAction {
  id: string;
  type: 'CREATE' | 'UPDATE' | 'DELETE';
  endpoint: string;
  data: any;
  timestamp: number;
  retries: number;
}
