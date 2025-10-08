import { useState, useEffect, useCallback } from 'react';
import { apiClient, ApiResponse } from '@/lib/api';

export interface UseApiOptions {
  onSuccess?: (data: any) => void;
  onError?: (error: string) => void;
  immediate?: boolean; // Auto-fetch on mount
}

export function useApi<T = any>(
  endpoint: string,
  options: UseApiOptions = {}
) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async (params?: Record<string, any>) => {
    setLoading(true);
    setError(null);

    const response: ApiResponse<T> = await apiClient.get(endpoint, params);

    if (response.status && response.data) {
      setData(response.data);
      options.onSuccess?.(response.data);
    } else {
      const errorMsg = response.message || 'Erro ao buscar dados';
      setError(errorMsg);
      options.onError?.(errorMsg);
    }

    setLoading(false);
  }, [endpoint, options]);

  const postData = useCallback(async (body: any) => {
    setLoading(true);
    setError(null);

    const response: ApiResponse<T> = await apiClient.post(endpoint, body);

    if (response.status && response.data) {
      setData(response.data);
      options.onSuccess?.(response.data);
    } else {
      const errorMsg = response.message || 'Erro ao enviar dados';
      setError(errorMsg);
      options.onError?.(errorMsg);
    }

    setLoading(false);
    return response;
  }, [endpoint, options]);

  const putData = useCallback(async (body: any) => {
    setLoading(true);
    setError(null);

    const response: ApiResponse<T> = await apiClient.put(endpoint, body);

    if (response.status && response.data) {
      setData(response.data);
      options.onSuccess?.(response.data);
    } else {
      const errorMsg = response.message || 'Erro ao atualizar dados';
      setError(errorMsg);
      options.onError?.(errorMsg);
    }

    setLoading(false);
    return response;
  }, [endpoint, options]);

  const deleteData = useCallback(async (params?: Record<string, any>) => {
    setLoading(true);
    setError(null);

    const response: ApiResponse<T> = await apiClient.delete(endpoint, params);

    if (response.status) {
      options.onSuccess?.(response.data);
    } else {
      const errorMsg = response.message || 'Erro ao deletar dados';
      setError(errorMsg);
      options.onError?.(errorMsg);
    }

    setLoading(false);
    return response;
  }, [endpoint, options]);

  useEffect(() => {
    if (options.immediate) {
      fetchData();
    }
  }, [options.immediate, fetchData]);

  return {
    data,
    loading,
    error,
    fetchData,
    postData,
    putData,
    deleteData,
    refetch: fetchData,
  };
}
