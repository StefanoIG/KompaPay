import { useState, useCallback, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useAPI } from './useAPI';
import { ENDPOINTS, STORAGE_KEYS, APP_CONFIG } from './config';
import {
  AuditLog,
  APIResponse,
  User,
  Grupo,
  Gasto,
} from './types';

// Hook para utilidades generales
export const useUtils = () => {
  const { get } = useAPI();

  // Formatear números como moneda
  const formatCurrency = useCallback((amount: number, currency: string = 'USD'): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(amount);
  }, []);

  // Formatear fechas
  const formatDate = useCallback((dateString: string, options?: Intl.DateTimeFormatOptions): string => {
    const date = new Date(dateString);
    const defaultOptions: Intl.DateTimeFormatOptions = {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    };
    return date.toLocaleDateString('es-ES', options || defaultOptions);
  }, []);

  // Formatear fecha y hora
  const formatDateTime = useCallback((dateString: string): string => {
    return formatDate(dateString, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }, [formatDate]);

  // Validar email
  const isValidEmail = useCallback((email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }, []);

  // Generar ID único
  const generateUniqueId = useCallback((): string => {
    return `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }, []);

  // Debounce para búsquedas
  const useDebounce = useCallback((value: string, delay: number) => {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
      const handler = setTimeout(() => {
        setDebouncedValue(value);
      }, delay);

      return () => {
        clearTimeout(handler);
      };
    }, [value, delay]);

    return debouncedValue;
  }, []);

  // Calcular dividir gastos equitativamente
  const calculateEqualSplit = useCallback((
    totalAmount: number,
    participantCount: number
  ): { amountPerPerson: number; remainder: number } => {
    const amountPerPerson = Math.floor((totalAmount * 100) / participantCount) / 100;
    const remainder = totalAmount - (amountPerPerson * participantCount);
    
    return {
      amountPerPerson,
      remainder: Math.round(remainder * 100) / 100,
    };
  }, []);

  // Calcular deudas entre usuarios
  const calculateDebts = useCallback((expenses: Gasto[], userId: string): {
    owes: { to: string; amount: number }[];
    owedBy: { from: string; amount: number }[];
  } => {
    const debts = new Map<string, number>();

    expenses.forEach(expense => {
      if (expense.participantes) {
        const sharePerPerson = expense.monto / expense.participantes.length;
        
        expense.participantes.forEach(participant => {
          if (participant.usuario_id !== expense.pagador.id) {
            const key = `${participant.usuario_id}-${expense.pagador.id}`;
            debts.set(key, (debts.get(key) || 0) + sharePerPerson);
          }
        });
      }
    });

    const owes: { to: string; amount: number }[] = [];
    const owedBy: { from: string; amount: number }[] = [];

    debts.forEach((amount, key) => {
      const [debtorId, creditorId] = key.split('-');
      
      if (debtorId === userId) {
        owes.push({ to: creditorId, amount });
      } else if (creditorId === userId) {
        owedBy.push({ from: debtorId, amount });
      }
    });

    return { owes, owedBy };
  }, []);

  // Validar datos de gasto
  const validateExpenseData = useCallback((data: {
    descripcion: string;
    monto: number;
    categoria: string;
    participantes: string[];
  }): { isValid: boolean; errors: string[] } => {
    const errors: string[] = [];

    if (!data.descripcion?.trim()) {
      errors.push('La descripción es requerida');
    }

    if (!data.monto || data.monto <= 0) {
      errors.push('El monto debe ser mayor a 0');
    }

    if (!data.categoria?.trim()) {
      errors.push('La categoría es requerida');
    }

    if (!data.participantes || data.participantes.length === 0) {
      errors.push('Debe haber al menos un participante');
    }

    return {
      isValid: errors.length === 0,
      errors,
    };
  }, []);

  return {
    formatCurrency,
    formatDate,
    formatDateTime,
    isValidEmail,
    generateUniqueId,
    useDebounce,
    calculateEqualSplit,
    calculateDebts,
    validateExpenseData,
  };
};

// Hook para gestión de caché local
export const useCache = () => {
  const [cacheState, setCacheState] = useState<Map<string, { data: any; timestamp: number }>>(new Map());

  // Guardar en caché
  const setCacheData = useCallback(async (key: string, data: any): Promise<void> => {
    const cacheItem = {
      data,
      timestamp: Date.now(),
    };

    setCacheState(prev => new Map(prev.set(key, cacheItem)));
    
    try {
      await AsyncStorage.setItem(`cache_${key}`, JSON.stringify(cacheItem));
    } catch (error) {
      console.error('Error guardando en caché:', error);
    }
  }, []);

  // Obtener del caché
  const getCacheData = useCallback(async (key: string): Promise<any | null> => {
    // Primero verificar en memoria
    const memoryItem = cacheState.get(key);
    if (memoryItem) {
      const isExpired = Date.now() - memoryItem.timestamp > APP_CONFIG.CACHE_DURATION;
      if (!isExpired) {
        return memoryItem.data;
      }
    }

    // Verificar en AsyncStorage
    try {
      const cachedItem = await AsyncStorage.getItem(`cache_${key}`);
      if (cachedItem) {
        const parsed = JSON.parse(cachedItem);
        const isExpired = Date.now() - parsed.timestamp > APP_CONFIG.CACHE_DURATION;
        
        if (!isExpired) {
          // Actualizar caché en memoria
          setCacheState(prev => new Map(prev.set(key, parsed)));
          return parsed.data;
        } else {
          // Limpiar caché expirado
          await AsyncStorage.removeItem(`cache_${key}`);
        }
      }
    } catch (error) {
      console.error('Error leyendo caché:', error);
    }

    return null;
  }, [cacheState]);

  // Limpiar caché
  const clearCacheData = useCallback(async (key?: string): Promise<void> => {
    if (key) {
      setCacheState(prev => {
        const newCache = new Map(prev);
        newCache.delete(key);
        return newCache;
      });
      
      try {
        await AsyncStorage.removeItem(`cache_${key}`);
      } catch (error) {
        console.error('Error limpiando caché:', error);
      }
    } else {
      // Limpiar todo el caché
      setCacheState(new Map());
      
      try {
        const keys = await AsyncStorage.getAllKeys();
        const cacheKeys = keys.filter(k => k.startsWith('cache_'));
        await AsyncStorage.multiRemove(cacheKeys);
      } catch (error) {
        console.error('Error limpiando todo el caché:', error);
      }
    }
  }, []);

  // Verificar si existe en caché
  const hasCacheData = useCallback(async (key: string): Promise<boolean> => {
    const cachedData = await getCacheData(key);
    return cachedData !== null;
  }, [getCacheData]);

  return {
    setCache: setCacheData,
    getCache: getCacheData,
    clearCache: clearCacheData,
    hasCache: hasCacheData,
  };
};

// Hook para logs de auditoría
export const useAudit = () => {
  const { get } = useAPI();
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Obtener logs del usuario
  const fetchUserLogs = useCallback(async (page: number = 1): Promise<void> => {
    try {
      setLoading(true);
      setError(null);

      const response: APIResponse<{
        data: AuditLog[];
        current_page: number;
        last_page: number;
      }> = await get(`${ENDPOINTS.AUDIT.USER_LOGS}?page=${page}`);

      if (response.success && response.data) {
        setLogs(page === 1 ? response.data.data : [...logs, ...response.data.data]);
      }
    } catch (err) {
      setError(err.message || 'Error al cargar logs');
    } finally {
      setLoading(false);
    }
  }, [get, logs]);

  // Obtener logs de un grupo
  const fetchGroupLogs = useCallback(async (
    groupId: string,
    page: number = 1
  ): Promise<AuditLog[]> => {
    try {
      const endpoint = ENDPOINTS.AUDIT.GROUP_LOGS.replace('{grupoId}', groupId);
      const response: APIResponse<{
        data: AuditLog[];
        current_page: number;
        last_page: number;
      }> = await get(`${endpoint}?page=${page}`);

      if (response.success && response.data) {
        return response.data.data;
      }
      return [];
    } catch (err) {
      throw new Error(err.message || 'Error al cargar logs del grupo');
    }
  }, [get]);

  return {
    logs,
    loading,
    error,
    fetchUserLogs,
    fetchGroupLogs,
  };
};

// Hook para conectividad de red
export const useNetworkStatus = () => {
  const [isConnected, setIsConnected] = useState(true);
  const [connectionType, setConnectionType] = useState<string>('wifi');

  // En React Native se usaría NetInfo, aquí simulamos
  useEffect(() => {
    const checkConnection = () => {
      setIsConnected(navigator.onLine);
    };

    window.addEventListener('online', checkConnection);
    window.addEventListener('offline', checkConnection);

    return () => {
      window.removeEventListener('online', checkConnection);
      window.removeEventListener('offline', checkConnection);
    };
  }, []);

  return {
    isConnected,
    connectionType,
  };
};
