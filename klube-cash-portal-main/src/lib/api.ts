// API configuration and utilities
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost';
const API_ENDPOINT = import.meta.env.VITE_API_ENDPOINT || '/api';

export const API_URL = `${API_BASE_URL}${API_ENDPOINT}`;

export interface ApiResponse<T = any> {
  status: boolean;
  data?: T;
  message?: string;
  error?: string;
}

export interface Transaction {
  id: number;
  date: string;
  client: string;
  code: string;
  value: number;
  status: 'pendente' | 'aprovado' | 'pago' | 'cancelado';
}

export interface DashboardKPI {
  totalVendas: number;
  valorTotal: number;
  pendentes: number;
  comissoes: number;
}

export interface StoreDetails {
  id: number;
  nome_fantasia: string;
  categoria: string;
  porcentagem_cashback: number;
  website?: string;
  descricao?: string;
  logo?: string;
}

class ApiClient {
  private baseUrl: string;

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl;
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          ...options.headers,
        },
        credentials: 'include', // Important for session cookies
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Erro na requisição');
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      return {
        status: false,
        message: error instanceof Error ? error.message : 'Erro desconhecido',
      };
    }
  }

  async get<T>(endpoint: string, params?: Record<string, any>): Promise<ApiResponse<T>> {
    const queryString = params
      ? '?' + new URLSearchParams(params).toString()
      : '';
    return this.request<T>(`${endpoint}${queryString}`, {
      method: 'GET',
    });
  }

  async post<T>(endpoint: string, data: any): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async put<T>(endpoint: string, data: any): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async delete<T>(endpoint: string, params?: Record<string, any>): Promise<ApiResponse<T>> {
    const queryString = params
      ? '?' + new URLSearchParams(params).toString()
      : '';
    return this.request<T>(`${endpoint}${queryString}`, {
      method: 'DELETE',
    });
  }
}

export const apiClient = new ApiClient(API_URL);
