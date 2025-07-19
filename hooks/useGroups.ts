import { useState, useCallback, useEffect } from 'react';
import { useAPI } from './useAPI';
import { ENDPOINTS } from './config';
import {
  Grupo,
  CreateGrupoRequest,
  UpdateGrupoRequest,
  JoinGroupRequest,
  InviteMemberRequest,
  GroupsState,
  APIResponse,
  UserSearchResult,
  FindUserRequest,
} from './types';

// Hook para gestión de grupos
export const useGroups = () => {
  const { get, post, put, del } = useAPI();
  const [groupsState, setGroupsState] = useState<GroupsState>({
    groups: [],
    currentGroup: null,
    loading: false,
    error: null,
    success: false,
  });

  // Obtener todos los grupos del usuario
  const fetchGroups = useCallback(async (): Promise<void> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Grupo[]> = await get(ENDPOINTS.USERS.MY_GROUPS);

      if (response.success && response.data) {
        setGroupsState(prev => ({
          ...prev,
          groups: response.data,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar grupos',
      }));
    }
  }, [get]);

  // Obtener detalles de un grupo específico
  const fetchGroupDetails = useCallback(async (groupId: string): Promise<Grupo | null> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Grupo> = await get(`${ENDPOINTS.GROUPS.SHOW}/${groupId}`);

      if (response.success && response.data) {
        const group = response.data;
        
        setGroupsState(prev => ({
          ...prev,
          currentGroup: group,
          // Actualizar el grupo en la lista también
          groups: prev.groups.map(g => g.id === groupId ? group : g),
          loading: false,
          success: true,
        }));

        return group;
      }
      return null;
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al cargar detalles del grupo',
      }));
      return null;
    }
  }, [get]);

  // Crear nuevo grupo
  const createGroup = useCallback(async (groupData: CreateGrupoRequest): Promise<Grupo | null> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Grupo> = await post(ENDPOINTS.GROUPS.CREATE, groupData);

      if (response.success && response.data) {
        const newGroup = response.data;
        
        setGroupsState(prev => ({
          ...prev,
          groups: [...prev.groups, newGroup],
          currentGroup: newGroup,
          loading: false,
          success: true,
        }));

        return newGroup;
      }
      return null;
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al crear grupo',
      }));
      throw error;
    }
  }, [post]);

  // Actualizar grupo
  const updateGroup = useCallback(async (
    groupId: string,
    updates: UpdateGrupoRequest
  ): Promise<Grupo | null> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Grupo> = await put(
        `${ENDPOINTS.GROUPS.UPDATE}/${groupId}`,
        updates
      );

      if (response.success && response.data) {
        const updatedGroup = response.data;
        
        setGroupsState(prev => ({
          ...prev,
          groups: prev.groups.map(g => g.id === groupId ? updatedGroup : g),
          currentGroup: prev.currentGroup?.id === groupId ? updatedGroup : prev.currentGroup,
          loading: false,
          success: true,
        }));

        return updatedGroup;
      }
      return null;
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al actualizar grupo',
      }));
      throw error;
    }
  }, [put]);

  // Eliminar grupo
  const deleteGroup = useCallback(async (groupId: string): Promise<void> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse = await del(`${ENDPOINTS.GROUPS.DELETE}/${groupId}`);

      if (response.success) {
        setGroupsState(prev => ({
          ...prev,
          groups: prev.groups.filter(g => g.id !== groupId),
          currentGroup: prev.currentGroup?.id === groupId ? null : prev.currentGroup,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al eliminar grupo',
      }));
      throw error;
    }
  }, [del]);

  // Unirse a un grupo por ID público
  const joinGroup = useCallback(async (joinData: JoinGroupRequest): Promise<Grupo | null> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const response: APIResponse<Grupo> = await post(ENDPOINTS.GROUPS.JOIN, joinData);

      if (response.success && response.data) {
        const joinedGroup = response.data;
        
        setGroupsState(prev => ({
          ...prev,
          groups: [...prev.groups, joinedGroup],
          currentGroup: joinedGroup,
          loading: false,
          success: true,
        }));

        return joinedGroup;
      }
      return null;
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al unirse al grupo',
      }));
      throw error;
    }
  }, [post]);

  // Invitar miembro al grupo
  const inviteMember = useCallback(async (
    groupId: string,
    inviteData: InviteMemberRequest
  ): Promise<void> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const endpoint = ENDPOINTS.GROUPS.INVITE_MEMBER.replace('{grupoId}', groupId);
      const response: APIResponse = await post(endpoint, inviteData);

      if (response.success) {
        setGroupsState(prev => ({
          ...prev,
          loading: false,
          success: true,
        }));
      }
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al enviar invitación',
      }));
      throw error;
    }
  }, [post]);

  // Agregar miembro al grupo
  const addMember = useCallback(async (
    groupId: string,
    userId: string
  ): Promise<void> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const endpoint = ENDPOINTS.GROUPS.ADD_MEMBER.replace('{grupoId}', groupId);
      const response: APIResponse = await post(endpoint, { user_id: userId });

      if (response.success) {
        // Recargar detalles del grupo para obtener la lista actualizada
        await fetchGroupDetails(groupId);
      }
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al agregar miembro',
      }));
      throw error;
    }
  }, [post, fetchGroupDetails]);

  // Remover miembro del grupo
  const removeMember = useCallback(async (
    groupId: string,
    userId: string
  ): Promise<void> => {
    try {
      setGroupsState(prev => ({ ...prev, loading: true, error: null }));

      const endpoint = ENDPOINTS.GROUPS.REMOVE_MEMBER
        .replace('{grupoId}', groupId)
        .replace('{usuarioId}', userId);
      
      const response: APIResponse = await del(endpoint);

      if (response.success) {
        // Recargar detalles del grupo para obtener la lista actualizada
        await fetchGroupDetails(groupId);
      }
    } catch (error) {
      setGroupsState(prev => ({
        ...prev,
        loading: false,
        error: error.message || 'Error al remover miembro',
      }));
      throw error;
    }
  }, [del, fetchGroupDetails]);

  // Buscar usuario por ID público
  const findUserByPublicId = useCallback(async (
    publicId: string
  ): Promise<UserSearchResult | null> => {
    try {
      const response: APIResponse<UserSearchResult> = await post(
        ENDPOINTS.USERS.FIND_BY_PUBLIC_ID,
        { id_publico: publicId } as FindUserRequest,
        false // Endpoint público
      );

      if (response.success && response.data) {
        return response.data;
      }
      return null;
    } catch (error) {
      throw new Error(error.message || 'Usuario no encontrado');
    }
  }, [post]);

  // Establecer grupo actual
  const setCurrentGroup = useCallback((group: Grupo | null) => {
    setGroupsState(prev => ({ ...prev, currentGroup: group }));
  }, []);

  // Limpiar errores y estados
  const clearGroupsError = useCallback(() => {
    setGroupsState(prev => ({ ...prev, error: null }));
  }, []);

  const clearGroupsSuccess = useCallback(() => {
    setGroupsState(prev => ({ ...prev, success: false }));
  }, []);

  // Cargar grupos automáticamente al montar el hook
  useEffect(() => {
    fetchGroups();
  }, [fetchGroups]);

  return {
    // Estado
    ...groupsState,
    
    // Acciones
    fetchGroups,
    fetchGroupDetails,
    createGroup,
    updateGroup,
    deleteGroup,
    joinGroup,
    inviteMember,
    addMember,
    removeMember,
    
    // Utils
    findUserByPublicId,
    setCurrentGroup,
    clearGroupsError,
    clearGroupsSuccess,
  };
};

// Hook específico para un grupo
export const useGroup = (groupId: string | null) => {
  const { fetchGroupDetails, currentGroup, loading, error } = useGroups();
  
  useEffect(() => {
    if (groupId) {
      fetchGroupDetails(groupId);
    }
  }, [groupId, fetchGroupDetails]);

  return {
    group: currentGroup,
    loading,
    error,
    refetch: () => groupId ? fetchGroupDetails(groupId) : Promise.resolve(null),
  };
};
