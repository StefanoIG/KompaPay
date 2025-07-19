import { useState, useCallback, useEffect } from 'react';
import { useAPI } from './useAPI';
import { ENDPOINTS } from './config';
import {
  Gasto,
  CreateGastoRequest,
  UpdateGastoRequest,
  PayDebtRequest,
  ExpensesState,
  APIResponse,
  DeudaResumen,
  GroupExpensesParams,
} from './types';

// Hook para gestión de gastos
export const useExpenses = () => {
  const { get, post, put, del } = useAPI();
  const [expensesState, setExpensesState] = useState<ExpensesState>({
    expenses: [],
    myExpenses: [],
    groupExpenses: [],
    currentExpense: null,
    debts: [],
    loading: false,
    error: null,
    success: false,
    hasMore: true,
    page: 1,
  });

  // Obtener mis gastos con paginación
  const fetchMyExpenses = useCallback(async (
    page: number = 1,
    refresh: boolean = false
  ): Promise<void> => {
    try {
      setExpensesState(prev => ({ 
        ...prev, 
        loading: true, 
        error: null,
        ...(refresh && { myExpenses: [], page: 1, hasMore: true })
      }));

      const endpoint = `${ENDPOINTS.EXPENSES.MY_EXPENSES}?page=${page}`;
      const response: APIResponse<{
        data: Gasto[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
      }> = await get(endpoint);

      if (response.success && response.data) {
        const { data: expenses, current_page, last_page } = response.data;
        
        setExpensesState(prev => ({
          ...prev,
          myExpenses: refresh ? expenses : [...prev.myExpenses, ...expenses],
          loading: false,
          success: true,
          page: current_page,
          hasMore: current_page < last_page,
        }));
      }
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar mis gastos',
      }));
    }
  }, [get]);

  // Obtener gastos de un grupo con filtros
  const fetchGroupExpenses = useCallback(async (
    groupId: string,
    params?: GroupExpensesParams,
    page: number = 1,
    refresh: boolean = false
  ): Promise<void> => {
    try {
      setExpensesState(prev => ({ 
        ...prev, 
        loading: true, 
        error: null,
        ...(refresh && { groupExpenses: [], page: 1, hasMore: true })
      }));

      let endpoint = `${ENDPOINTS.EXPENSES.GROUP_EXPENSES.replace('{grupoId}', groupId)}?page=${page}`;
      
      // Agregar parámetros de filtro si existen
      if (params) {
        const queryParams = new URLSearchParams();
        if (params.desde) queryParams.append('desde', params.desde);
        if (params.hasta) queryParams.append('hasta', params.hasta);
        if (params.categoria) queryParams.append('categoria', params.categoria);
        if (params.pagador_id) queryParams.append('pagador_id', params.pagador_id);
        if (params.participante_id) queryParams.append('participante_id', params.participante_id);
        
        const paramString = queryParams.toString();
        if (paramString) {
          endpoint += `&${paramString}`;
        }
      }

      const response: APIResponse<{
        data: Gasto[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
      }> = await get(endpoint);

      if (response.success && response.data) {
        const { data: expenses, current_page, last_page } = response.data;
        
        setExpensesState(prev => ({
          ...prev,
          groupExpenses: refresh ? expenses : [...prev.groupExpenses, ...expenses],
          loading: false,
          success: true,
          page: current_page,
          hasMore: current_page < last_page,
        }));
      }
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar gastos del grupo',
      }));
    }
  }, [get]);

  // Obtener detalles de un gasto específico
  const fetchExpenseDetails = useCallback(async (expenseId: string): Promise<Gasto | null> => {
    try {
      setExpensesState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Gasto> = await get(`${ENDPOINTS.EXPENSES.SHOW}/${expenseId}`);

      if (response.success && response.data) {
        const expense = response.data;
        
        setExpensesState(prev => ({
          ...prev,
          currentExpense: expense,
          loading: false,
          success: true,
        }));

        return expense;
      }
      return null;
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar detalles del gasto',
      }));
      return null;
    }
  }, [get]);

  // Crear nuevo gasto
  const createExpense = useCallback(async (
    expenseData: CreateGastoRequest
  ): Promise<Gasto | null> => {
    try {
      setExpensesState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Gasto> = await post(ENDPOINTS.EXPENSES.CREATE, expenseData);

      if (response.success && response.data) {
        const newExpense = response.data;
        
        setExpensesState(prev => ({
          ...prev,
          myExpenses: [newExpense, ...prev.myExpenses],
          groupExpenses: [newExpense, ...prev.groupExpenses],
          currentExpense: newExpense,
          loading: false,
          success: true,
        }));

        return newExpense;
      }
      return null;
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al crear gasto',
      }));
      throw error;
    }
  }, [post]);

  // Actualizar gasto
  const updateExpense = useCallback(async (
    expenseId: string,
    updates: UpdateGastoRequest
  ): Promise<Gasto | null> => {
    try {
      setExpensesState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Gasto> = await put(
        `${ENDPOINTS.EXPENSES.UPDATE}/${expenseId}`,
        updates
      );

      if (response.success && response.data) {
        const updatedExpense = response.data;
        
        setExpensesState(prev => ({
          ...prev,
          myExpenses: prev.myExpenses.map(e => e.id === expenseId ? updatedExpense : e),
          groupExpenses: prev.groupExpenses.map(e => e.id === expenseId ? updatedExpense : e),
          currentExpense: prev.currentExpense?.id === expenseId ? updatedExpense : prev.currentExpense,
          loading: false,
          success: true,
        }));

        return updatedExpense;
      }
      return null;
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al actualizar gasto',
      }));
      throw error;
    }
  }, [put]);

  // Eliminar gasto
  const deleteExpense = useCallback(async (expenseId: string): Promise<void> => {
    try {
      setExpensesState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse = await del(`${ENDPOINTS.EXPENSES.DELETE}/${expenseId}`);

      if (response.success) {
        setExpensesState(prev => ({
          ...prev,
          myExpenses: prev.myExpenses.filter(e => e.id !== expenseId),
          groupExpenses: prev.groupExpenses.filter(e => e.id !== expenseId),
          currentExpense: prev.currentExpense?.id === expenseId ? null : prev.currentExpense,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al eliminar gasto',
      }));
      throw error;
    }
  }, [del]);

  // Obtener resumen de deudas del usuario
  const fetchMyDebts = useCallback(async (): Promise<void> => {
    try {
      setExpensesState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<DeudaResumen[]> = await get(ENDPOINTS.EXPENSES.MY_DEBTS);

      if (response.success && response.data) {
        setExpensesState(prev => ({
          ...prev,
          debts: response.data,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar deudas',
      }));
    }
  }, [get]);

  // Obtener deudas específicas de un grupo
  const fetchGroupDebts = useCallback(async (groupId: string): Promise<DeudaResumen[]> => {
    try {
      const endpoint = ENDPOINTS.EXPENSES.GROUP_DEBTS.replace('{grupoId}', groupId);
      const response: APIResponse<DeudaResumen[]> = await get(endpoint);

      if (response.success && response.data) {
        return response.data;
      }
      return [];
    } catch (error) {
      throw new Error(error.message || 'Error al cargar deudas del grupo');
    }
  }, [get]);

  // Pagar deuda
  const payDebt = useCallback(async (
    debtData: PayDebtRequest
  ): Promise<void> => {
    try {
      setExpensesState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse = await post(ENDPOINTS.EXPENSES.PAY_DEBT, debtData);

      if (response.success) {
        // Actualizar deudas después del pago
        await fetchMyDebts();
        
        setExpensesState(prev => ({
          ...prev,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setExpensesState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al procesar pago',
      }));
      throw error;
    }
  }, [post, fetchMyDebts]);

  // Buscar gastos globalmente
  const searchExpenses = useCallback(async (
    searchTerm: string,
    filters?: {
      grupoId?: string;
      categoria?: string;
      fechaDesde?: string;
      fechaHasta?: string;
    }
  ): Promise<Gasto[]> => {
    try {
      const queryParams = new URLSearchParams({ search: searchTerm });
      
      if (filters) {
        if (filters.grupoId) queryParams.append('grupo_id', filters.grupoId);
        if (filters.categoria) queryParams.append('categoria', filters.categoria);
        if (filters.fechaDesde) queryParams.append('fecha_desde', filters.fechaDesde);
        if (filters.fechaHasta) queryParams.append('fecha_hasta', filters.fechaHasta);
      }

      const endpoint = `${ENDPOINTS.EXPENSES.SEARCH}?${queryParams.toString()}`;
      const response: APIResponse<Gasto[]> = await get(endpoint);

      if (response.success && response.data) {
        return response.data;
      }
      return [];
    } catch (error) {
      throw new Error(error.message || 'Error en la búsqueda');
    }
  }, [get]);

  // Cargar más elementos (paginación)
  const loadMore = useCallback(async (type: 'my' | 'group', groupId?: string) => {
    if (expensesState.loading || !expensesState.hasMore) return;

    const nextPage = expensesState.page + 1;

    if (type === 'my') {
      await fetchMyExpenses(nextPage);
    } else if (type === 'group' && groupId) {
      await fetchGroupExpenses(groupId, undefined, nextPage);
    }
  }, [expensesState.loading, expensesState.hasMore, expensesState.page, fetchMyExpenses, fetchGroupExpenses]);

  // Refrescar listas
  const refreshExpenses = useCallback(async (type: 'my' | 'group', groupId?: string) => {
    if (type === 'my') {
      await fetchMyExpenses(1, true);
    } else if (type === 'group' && groupId) {
      await fetchGroupExpenses(groupId, undefined, 1, true);
    }
  }, [fetchMyExpenses, fetchGroupExpenses]);

  // Establecer gasto actual
  const setCurrentExpense = useCallback((expense: Gasto | null) => {
    setExpensesState(prev => ({ ...prev, currentExpense: expense }));
  }, []);

  // Limpiar errores y estados
  const clearExpensesError = useCallback(() => {
    setExpensesState(prev => ({ ...prev, error: null }));
  }, []);

  const clearExpensesSuccess = useCallback(() => {
    setExpensesState(prev => ({ ...prev, success: false }));
  }, []);

  return {
    // Estado
    ...expensesState,
    
    // Acciones principales
    fetchMyExpenses,
    fetchGroupExpenses,
    fetchExpenseDetails,
    createExpense,
    updateExpense,
    deleteExpense,
    
    // Deudas
    fetchMyDebts,
    fetchGroupDebts,
    payDebt,
    
    // Búsqueda
    searchExpenses,
    
    // Paginación
    loadMore,
    refreshExpenses,
    
    // Utils
    setCurrentExpense,
    clearExpensesError,
    clearExpensesSuccess,
  };
};

// Hook específico para un gasto
export const useExpense = (expenseId: string | null) => {
  const { fetchExpenseDetails, currentExpense, loading, error } = useExpenses();
  
  useEffect(() => {
    if (expenseId) {
      fetchExpenseDetails(expenseId);
    }
  }, [expenseId, fetchExpenseDetails]);

  return {
    expense: currentExpense,
    loading,
    error,
    refetch: () => expenseId ? fetchExpenseDetails(expenseId) : Promise.resolve(null),
  };
};

// Hook para deudas de un grupo específico
export const useGroupDebts = (groupId: string | null) => {
  const [debts, setDebts] = useState<DeudaResumen[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { fetchGroupDebts } = useExpenses();

  const loadDebts = useCallback(async () => {
    if (!groupId) return;

    try {
      setLoading(true);
      setError(null);
      const groupDebts = await fetchGroupDebts(groupId);
      setDebts(groupDebts);
    } catch (err) {
      setError(err.message || 'Error al cargar deudas del grupo');
    } finally {
      setLoading(false);
    }
  }, [groupId, fetchGroupDebts]);

  useEffect(() => {
    loadDebts();
  }, [loadDebts]);

  return {
    debts,
    loading,
    error,
    refetch: loadDebts,
  };
};
