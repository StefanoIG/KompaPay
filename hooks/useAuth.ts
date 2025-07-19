import { useState, useCallback, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useAPI } from './useAPI';
import { ENDPOINTS, STORAGE_KEYS } from './config';
import {
  User,
  LoginRequest,
  RegisterRequest,
  UpdateUserRequest,
  LoginResponse,
  AuthState,
  APIResponse,
} from './types';

// Hook para autenticación
export const useAuth = () => {
  const { post, put, clearError } = useAPI();
  const [authState, setAuthState] = useState<AuthState>({
    user: null,
    token: null,
    isAuthenticated: false,
    loading: true,
    error: null,
    success: false,
  });

  // Cargar datos de autenticación desde AsyncStorage al inicializar
  useEffect(() => {
    loadStoredAuth();
  }, []);

  const loadStoredAuth = useCallback(async () => {
    try {
      setAuthState(prev => ({ ...prev, loading: true }));

      const [storedToken, storedUser] = await Promise.all([
        AsyncStorage.getItem(STORAGE_KEYS.AUTH_TOKEN),
        AsyncStorage.getItem(STORAGE_KEYS.USER_DATA),
      ]);

      if (storedToken && storedUser) {
        const user: User = JSON.parse(storedUser);
        setAuthState(prev => ({
          ...prev,
          user,
          token: storedToken,
          isAuthenticated: true,
          loading: false,
        }));
      } else {
        setAuthState(prev => ({
          ...prev,
          loading: false,
          isAuthenticated: false,
        }));
      }
    } catch (error) {
      console.error('Error loading stored auth:', error);
      setAuthState(prev => ({
        ...prev,
        loading: false,
        error: 'Error al cargar sesión guardada',
      }));
    }
  }, []);

  // Guardar datos de autenticación en AsyncStorage
  const saveAuthData = useCallback(async (user: User, token: string) => {
    try {
      await Promise.all([
        AsyncStorage.setItem(STORAGE_KEYS.AUTH_TOKEN, token),
        AsyncStorage.setItem(STORAGE_KEYS.USER_DATA, JSON.stringify(user)),
      ]);
    } catch (error) {
      console.error('Error saving auth data:', error);
    }
  }, []);

  // Limpiar datos de autenticación
  const clearAuthData = useCallback(async () => {
    try {
      await Promise.all([
        AsyncStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN),
        AsyncStorage.removeItem(STORAGE_KEYS.USER_DATA),
      ]);
    } catch (error) {
      console.error('Error clearing auth data:', error);
    }
  }, []);

  // Login
  const login = useCallback(async (credentials: LoginRequest): Promise<void> => {
    try {
      setAuthState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<LoginResponse> = await post(
        ENDPOINTS.AUTH.LOGIN,
        credentials,
        false // No requiere autenticación
      );

      if (response.success && response.data) {
        const { user, token } = response.data;
        
        await saveAuthData(user, token);
        
        setAuthState(prev => ({
          ...prev,
          user,
          token,
          isAuthenticated: true,
          loading: false,
          success: true,
        }));
      } else {
        throw new Error(response.message || 'Error en el login');
      }
    } catch (error) {
      setAuthState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al iniciar sesión',
        isAuthenticated: false,
      }));
      throw error;
    }
  }, [post, saveAuthData]);

  // Registro
  const register = useCallback(async (userData: RegisterRequest): Promise<void> => {
    try {
      setAuthState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<LoginResponse> = await post(
        ENDPOINTS.AUTH.REGISTER,
        userData,
        false // No requiere autenticación
      );

      if (response.success && response.data) {
        const { user, token } = response.data;
        
        await saveAuthData(user, token);
        
        setAuthState(prev => ({
          ...prev,
          user,
          token,
          isAuthenticated: true,
          loading: false,
          success: true,
        }));
      } else {
        throw new Error(response.message || 'Error en el registro');
      }
    } catch (error) {
      setAuthState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al registrar usuario',
        isAuthenticated: false,
      }));
      throw error;
    }
  }, [post, saveAuthData]);

  // Logout
  const logout = useCallback(async (): Promise<void> => {
    try {
      setAuthState(prev => ({ ...prev, loading: true }));

      // Intentar logout en el servidor (opcional, no bloquear si falla)
      try {
        await post(ENDPOINTS.AUTH.LOGOUT);
      } catch (error) {
        console.warn('Error logging out from server:', error);
      }

      // Limpiar datos locales siempre
      await clearAuthData();
      
      setAuthState({
        user: null,
        token: null,
        isAuthenticated: false,
        loading: false,
        error: null,
        success: true,
      });
    } catch (error) {
      console.error('Error during logout:', error);
      setAuthState(prev => ({
        ...prev,
        loading: false,
        error: 'Error al cerrar sesión',
      }));
    }
  }, [post, clearAuthData]);

  // Actualizar perfil
  const updateProfile = useCallback(async (updates: UpdateUserRequest): Promise<void> => {
    try {
      setAuthState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<User> = await put(
        ENDPOINTS.AUTH.UPDATE_PROFILE,
        updates
      );

      if (response.success && response.data) {
        const updatedUser = response.data;
        
        // Actualizar AsyncStorage
        await AsyncStorage.setItem(
          STORAGE_KEYS.USER_DATA,
          JSON.stringify(updatedUser)
        );
        
        setAuthState(prev => ({
          ...prev,
          user: updatedUser,
          loading: false,
          success: true,
        }));
      } else {
        throw new Error(response.message || 'Error al actualizar perfil');
      }
    } catch (error) {
      setAuthState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al actualizar perfil',
      }));
      throw error;
    }
  }, [put]);

  // Obtener perfil actual del servidor
  const refreshProfile = useCallback(async (): Promise<void> => {
    try {
      setAuthState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<User> = await post(ENDPOINTS.AUTH.ME);

      if (response.success && response.data) {
        const user = response.data;
        
        // Actualizar AsyncStorage
        await AsyncStorage.setItem(
          STORAGE_KEYS.USER_DATA,
          JSON.stringify(user)
        );
        
        setAuthState(prev => ({
          ...prev,
          user,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setAuthState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al actualizar perfil',
      }));
      throw error;
    }
  }, [post]);

  // Limpiar errores y estados
  const clearAuthError = useCallback(() => {
    setAuthState(prev => ({ ...prev, error: null }));
    clearError();
  }, [clearError]);

  const clearAuthSuccess = useCallback(() => {
    setAuthState(prev => ({ ...prev, success: false }));
  }, []);

  return {
    // Estado
    ...authState,
    
    // Acciones
    login,
    register,
    logout,
    updateProfile,
    refreshProfile,
    
    // Utils
    clearAuthError,
    clearAuthSuccess,
    loadStoredAuth,
  };
};

// Hook para verificar si el usuario está autenticado
export const useAuthStatus = () => {
  const { isAuthenticated, loading, user } = useAuth();
  
  return {
    isAuthenticated,
    isLoading: loading,
    user,
    isGuest: !isAuthenticated && !loading,
  };
};
