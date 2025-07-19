import { useState, useCallback, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useAPI } from './useAPI';
import { ENDPOINTS, STORAGE_KEYS } from './config';
import {
  SyncConflicto,
  SyncState,
  APIResponse,
  OfflineAction,
  SyncData,
  ResolveSyncConflictRequest,
} from './types';

// Hook para gestión de sincronización y conflictos
export const useSync = () => {
  const { get, post, put } = useAPI();
  const [syncState, setSyncState] = useState<SyncState>({
    lastSync: null,
    pendingChanges: 0,
    conflicts: [],
    loading: false,
    error: null,
    success: false,
  });

  const [offlineActions, setOfflineActions] = useState<OfflineAction[]>([]);

  // Cargar datos de sincronización desde AsyncStorage
  const loadSyncData = useCallback(async () => {
    try {
      const [lastSyncStr, offlineActionsStr] = await Promise.all([
        AsyncStorage.getItem(STORAGE_KEYS.LAST_SYNC),
        AsyncStorage.getItem(STORAGE_KEYS.OFFLINE_ACTIONS),
      ]);

      const lastSync = lastSyncStr || null;
      const actions: OfflineAction[] = offlineActionsStr ? JSON.parse(offlineActionsStr) : [];

      setSyncState(prev => ({
        ...prev,
        lastSync,
        pendingChanges: actions.length,
      }));

      setOfflineActions(actions);
    } catch (error) {
      console.error('Error cargando datos de sincronización:', error);
    }
  }, []);

  // Guardar timestamp de última sincronización
  const updateLastSync = useCallback(async (timestamp?: string) => {
    try {
      const syncTime = timestamp || new Date().toISOString();
      await AsyncStorage.setItem(STORAGE_KEYS.LAST_SYNC, syncTime);
      
      setSyncState(prev => ({
        ...prev,
        lastSync: syncTime,
      }));
    } catch (error) {
      console.error('Error guardando timestamp de sincronización:', error);
    }
  }, []);

  // Agregar acción offline
  const addOfflineAction = useCallback(async (action: Omit<OfflineAction, 'id' | 'timestamp' | 'retries'>) => {
    try {
      const newAction: OfflineAction = {
        ...action,
        id: `offline_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
        timestamp: Date.now(),
        retries: 0,
      };

      const updatedActions = [...offlineActions, newAction];
      setOfflineActions(updatedActions);
      
      await AsyncStorage.setItem(STORAGE_KEYS.OFFLINE_ACTIONS, JSON.stringify(updatedActions));
      
      setSyncState(prev => ({
        ...prev,
        pendingChanges: updatedActions.length,
      }));
    } catch (error) {
      console.error('Error guardando acción offline:', error);
    }
  }, [offlineActions]);

  // Remover acción offline
  const removeOfflineAction = useCallback(async (actionId: string) => {
    try {
      const updatedActions = offlineActions.filter(action => action.id !== actionId);
      setOfflineActions(updatedActions);
      
      await AsyncStorage.setItem(STORAGE_KEYS.OFFLINE_ACTIONS, JSON.stringify(updatedActions));
      
      setSyncState(prev => ({
        ...prev,
        pendingChanges: updatedActions.length,
      }));
    } catch (error) {
      console.error('Error removiendo acción offline:', error);
    }
  }, [offlineActions]);

  // Obtener datos de sincronización del servidor
  const fetchSyncData = useCallback(async (): Promise<SyncData | null> => {
    try {
      setSyncState(prev => ({ ...prev, loading: true, error: null }));

      const params = syncState.lastSync ? `?since=${encodeURIComponent(syncState.lastSync)}` : '';
      const response: APIResponse<SyncData> = await get(`${ENDPOINTS.SYNC.DATA}${params}`);

      if (response.success && response.data) {
        setSyncState(prev => ({
          ...prev,
          loading: false,
          success: true,
        }));

        return response.data;
      }
      return null;
    } catch (error) {
      setSyncState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al obtener datos de sincronización',
      }));
      return null;
    }
  }, [get, syncState.lastSync]);

  // Enviar cambios locales al servidor
  const pushLocalChanges = useCallback(async (): Promise<boolean> => {
    if (offlineActions.length === 0) return true;

    try {
      setSyncState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse = await post(ENDPOINTS.SYNC.PUSH, {
        actions: offlineActions,
      });

      if (response.success) {
        // Limpiar acciones offline exitosas
        await AsyncStorage.removeItem(STORAGE_KEYS.OFFLINE_ACTIONS);
        setOfflineActions([]);
        
        setSyncState(prev => ({
          ...prev,
          pendingChanges: 0,
          loading: false,
          success: true,
        }));

        return true;
      }
      return false;
    } catch (error) {
      setSyncState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al enviar cambios',
      }));
      return false;
    }
  }, [post, offlineActions]);

  // Obtener conflictos del usuario
  const fetchConflicts = useCallback(async (): Promise<void> => {
    try {
      setSyncState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<SyncConflicto[]> = await get(ENDPOINTS.USERS.MY_CONFLICTS);

      if (response.success && response.data) {
        setSyncState(prev => ({
          ...prev,
          conflicts: response.data,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setSyncState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar conflictos',
      }));
    }
  }, [get]);

  // Resolver conflicto de sincronización
  const resolveConflict = useCallback(async (
    conflictId: string,
    resolution: ResolveSyncConflictRequest
  ): Promise<void> => {
    try {
      setSyncState(prev => ({ ...prev, loading: true, error: null }));

      const endpoint = `${ENDPOINTS.SYNC.RESOLVE_CONFLICT}/${conflictId}`;
      const response: APIResponse = await post(endpoint, resolution);

      if (response.success) {
        // Remover conflicto resuelto de la lista
        setSyncState(prev => ({
          ...prev,
          conflicts: prev.conflicts.filter(c => c.id !== conflictId),
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setSyncState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al resolver conflicto',
      }));
      throw error;
    }
  }, [post]);

  // Sincronización completa
  const performFullSync = useCallback(async (): Promise<{
    success: boolean;
    hasConflicts: boolean;
    newData?: SyncData;
  }> => {
    try {
      setSyncState(prev => ({ ...prev, loading: true, error: null }));

      // 1. Enviar cambios locales
      const pushSuccess = await pushLocalChanges();
      
      // 2. Obtener datos del servidor
      const newData = await fetchSyncData();
      
      // 3. Obtener conflictos
      await fetchConflicts();
      
      // 4. Actualizar timestamp de sincronización si todo fue exitoso
      if (pushSuccess && newData) {
        await updateLastSync();
      }

      setSyncState(prev => ({
        ...prev,
        loading: false,
        success: pushSuccess && !!newData,
      }));

      return {
        success: pushSuccess && !!newData,
        hasConflicts: syncState.conflicts.length > 0,
        newData: newData || undefined,
      };
    } catch (error) {
      setSyncState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error en sincronización',
      }));
      
      return {
        success: false,
        hasConflicts: false,
      };
    }
  }, [pushLocalChanges, fetchSyncData, fetchConflicts, updateLastSync, syncState.conflicts.length]);

  // Retry de acciones offline fallidas
  const retryFailedActions = useCallback(async (): Promise<void> => {
    const failedActions = offlineActions.filter(action => action.retries > 0);
    
    for (const action of failedActions) {
      try {
        // Intentar ejecutar la acción nuevamente
        let response: APIResponse;
        
        switch (action.type) {
          case 'CREATE':
            response = await post(action.endpoint, action.data);
            break;
          case 'UPDATE':
            response = await put(action.endpoint, action.data);
            break;
          case 'DELETE':
            response = await get(action.endpoint); // Assuming DELETE uses GET with different endpoint
            break;
          default:
            continue;
        }

        if (response.success) {
          await removeOfflineAction(action.id);
        } else {
          // Incrementar contador de reintentos
          const updatedActions = offlineActions.map(a => 
            a.id === action.id ? { ...a, retries: a.retries + 1 } : a
          );
          setOfflineActions(updatedActions);
          await AsyncStorage.setItem(STORAGE_KEYS.OFFLINE_ACTIONS, JSON.stringify(updatedActions));
        }
      } catch (error) {
        console.error('Error en retry de acción offline:', error);
      }
    }
  }, [offlineActions, post, put, get, removeOfflineAction]);

  // Limpiar datos de sincronización
  const clearSyncData = useCallback(async () => {
    try {
      await Promise.all([
        AsyncStorage.removeItem(STORAGE_KEYS.LAST_SYNC),
        AsyncStorage.removeItem(STORAGE_KEYS.OFFLINE_ACTIONS),
      ]);

      setSyncState({
        lastSync: null,
        pendingChanges: 0,
        conflicts: [],
        loading: false,
        error: null,
        success: false,
      });

      setOfflineActions([]);
    } catch (error) {
      console.error('Error limpiando datos de sincronización:', error);
    }
  }, []);

  // Limpiar errores
  const clearSyncError = useCallback(() => {
    setSyncState(prev => ({ ...prev, error: null }));
  }, []);

  const clearSyncSuccess = useCallback(() => {
    setSyncState(prev => ({ ...prev, success: false }));
  }, []);

  // Cargar datos al montar el hook
  useEffect(() => {
    loadSyncData();
  }, [loadSyncData]);

  return {
    // Estado
    ...syncState,
    offlineActions,
    
    // Acciones principales
    performFullSync,
    fetchSyncData,
    pushLocalChanges,
    
    // Gestión de conflictos
    fetchConflicts,
    resolveConflict,
    
    // Gestión offline
    addOfflineAction,
    removeOfflineAction,
    retryFailedActions,
    
    // Utils
    updateLastSync,
    clearSyncData,
    clearSyncError,
    clearSyncSuccess,
    loadSyncData,
  };
};

// Hook para conflictos específicos
export const useConflicts = () => {
  const { conflicts, loading, error, fetchConflicts, resolveConflict } = useSync();

  return {
    conflicts,
    loading,
    error,
    fetchConflicts,
    resolveConflict,
    hasConflicts: conflicts.length > 0,
  };
};

// Hook para modo offline
export const useOffline = () => {
  const { offlineActions, addOfflineAction, removeOfflineAction, retryFailedActions, pendingChanges } = useSync();

  return {
    offlineActions,
    addOfflineAction,
    removeOfflineAction,
    retryFailedActions,
    pendingChanges,
    hasOfflineActions: offlineActions.length > 0,
  };
};
