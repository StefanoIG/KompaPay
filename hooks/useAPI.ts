import { useState, useCallback } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE_URL, API_CONFIG, APIErrorType, STORAGE_KEYS } from './config';
import { APIResponse, LoadingState } from './types';

// Clase personalizada para errores de la API
export class APIError extends Error {
  type: APIErrorType;
  statusCode?: number;
  details?: any;

  constructor(message: string, type: APIErrorType, statusCode?: number, details?: any) {
    super(message);
    this.name = 'APIError';
    this.type = type;
    this.statusCode = statusCode;
    this.details = details;
  }
}

// Hook base para peticiones HTTP
export const useAPI = () => {
  const [state, setState] = useState<LoadingState>({
    loading: false,
    error: null,
    success: false,
  });

  // Obtener token de autenticación
  const getAuthToken = useCallback(async (): Promise<string | null> => {
    try {
      return await AsyncStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
    } catch (error) {
      console.error('Error getting auth token:', error);
      return null;
    }
  }, []);

  // Configurar headers con autenticación
  const getHeaders = useCallback(async (additionalHeaders?: Record<string, string>) => {
    const token = await getAuthToken();
    const headers = {
      ...API_CONFIG.DEFAULT_HEADERS,
      ...additionalHeaders,
    };

    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    return headers;
  }, [getAuthToken]);

  // Función principal para hacer peticiones
  const request = useCallback(async <T = any>(
    endpoint: string,
    options: {
      method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
      data?: any;
      headers?: Record<string, string>;
      requireAuth?: boolean;
    } = {}
  ): Promise<APIResponse<T>> => {
    const {
      method = 'GET',
      data,
      headers: additionalHeaders,
      requireAuth = true,
    } = options;

    setState(prev => ({ ...prev, loading: true, error: null, success: false }));

    try {
      const url = `${BASE_URL}${endpoint}`;
      const headers = await getHeaders(additionalHeaders);

      // Verificar autenticación si es requerida
      if (requireAuth && !headers.Authorization) {
        throw new APIError(
          'Token de autenticación requerido',
          APIErrorType.AUTHENTICATION_ERROR,
          401
        );
      }

      const requestOptions: RequestInit = {
        method,
        headers,
      };

      // Agregar body para métodos que lo soportan
      if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
        requestOptions.body = JSON.stringify(data);
      }

      // Hacer la petición con timeout
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.TIMEOUT);

      const response = await fetch(url, {
        ...requestOptions,
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      // Parsear respuesta JSON
      let responseData: APIResponse<T>;
      try {
        responseData = await response.json();
      } catch (parseError) {
        throw new APIError(
          'Error al parsear respuesta del servidor',
          APIErrorType.SERVER_ERROR,
          response.status
        );
      }

      // Manejar errores HTTP
      if (!response.ok) {
        let errorType = APIErrorType.SERVER_ERROR;
        
        switch (response.status) {
          case 401:
            errorType = APIErrorType.AUTHENTICATION_ERROR;
            // Limpiar token inválido
            await AsyncStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
            break;
          case 403:
            errorType = APIErrorType.AUTHORIZATION_ERROR;
            break;
          case 422:
            errorType = APIErrorType.VALIDATION_ERROR;
            break;
          case 500:
          case 502:
          case 503:
          case 504:
            errorType = APIErrorType.SERVER_ERROR;
            break;
          default:
            errorType = APIErrorType.UNKNOWN_ERROR;
        }

        throw new APIError(
          responseData.message || `Error ${response.status}`,
          errorType,
          response.status,
          responseData.errors
        );
      }

      setState(prev => ({ ...prev, loading: false, success: true }));
      return responseData;

    } catch (error) {
      let apiError: APIError;

      if (error instanceof APIError) {
        apiError = error;
      } else if (error.name === 'AbortError') {
        apiError = new APIError(
          'Tiempo de espera agotado',
          APIErrorType.TIMEOUT_ERROR
        );
      } else if (error.message.includes('Network')) {
        apiError = new APIError(
          'Error de conexión',
          APIErrorType.NETWORK_ERROR
        );
      } else {
        apiError = new APIError(
          error.message || 'Error desconocido',
          APIErrorType.UNKNOWN_ERROR
        );
      }

      setState(prev => ({ 
        ...prev, 
        loading: false, 
        error: apiError.message,
        success: false 
      }));
      
      throw apiError;
    }
  }, [getHeaders]);

  // Métodos de conveniencia
  const get = useCallback(<T = any>(endpoint: string, requireAuth = true) => 
    request<T>(endpoint, { method: 'GET', requireAuth }), [request]);

  const post = useCallback(<T = any>(endpoint: string, data?: any, requireAuth = true) => 
    request<T>(endpoint, { method: 'POST', data, requireAuth }), [request]);

  const put = useCallback(<T = any>(endpoint: string, data?: any, requireAuth = true) => 
    request<T>(endpoint, { method: 'PUT', data, requireAuth }), [request]);

  const del = useCallback(<T = any>(endpoint: string, requireAuth = true) => 
    request<T>(endpoint, { method: 'DELETE', requireAuth }), [request]);

  // Limpiar estado
  const clearError = useCallback(() => {
    setState(prev => ({ ...prev, error: null }));
  }, []);

  const clearSuccess = useCallback(() => {
    setState(prev => ({ ...prev, success: false }));
  }, []);

  return {
    ...state,
    request,
    get,
    post,
    put,
    delete: del,
    clearError,
    clearSuccess,
  };
};

// Hook para manejar estados de carga específicos
export const useLoadingState = (initialState: Partial<LoadingState> = {}) => {
  const [state, setState] = useState<LoadingState>({
    loading: false,
    error: null,
    success: false,
    ...initialState,
  });

  const setLoading = useCallback((loading: boolean) => {
    setState(prev => ({ ...prev, loading, error: null, success: false }));
  }, []);

  const setError = useCallback((error: string | null) => {
    setState(prev => ({ ...prev, loading: false, error, success: false }));
  }, []);

  const setSuccess = useCallback((success: boolean) => {
    setState(prev => ({ ...prev, loading: false, error: null, success }));
  }, []);

  const reset = useCallback(() => {
    setState({ loading: false, error: null, success: false });
  }, []);

  return {
    ...state,
    setLoading,
    setError,
    setSuccess,
    reset,
  };
};
