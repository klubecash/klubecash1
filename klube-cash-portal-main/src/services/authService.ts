import { apiClient, ApiResponse } from '@/lib/api';

export interface LoginResponse {
  user_data?: {
    id: number;
    nome: string;
    email: string;
    tipo: string;
  };
}

export interface UserSession {
  id: number;
  name: string;
  email: string;
  type: string;
  storeId?: number;
  storeName?: string;
}

class AuthService {
  private currentUser: UserSession | null = null;

  async login(email: string, password: string): Promise<ApiResponse<LoginResponse>> {
    const response = await apiClient.post<LoginResponse>('/auth-login.php', {
      email,
      password
    });

    if (response.status && response.data?.user_data) {
      // Armazenar dados do usuário
      this.currentUser = {
        id: response.data.user_data.id,
        name: response.data.user_data.nome,
        email: response.data.user_data.email,
        type: response.data.user_data.tipo
      };

      // Salvar no localStorage para persistência
      localStorage.setItem('klube_user', JSON.stringify(this.currentUser));
    }

    return response;
  }

  async logout(): Promise<ApiResponse> {
    const response = await apiClient.post('/auth-logout.php', {});

    // Limpar dados locais independente da resposta
    this.currentUser = null;
    localStorage.removeItem('klube_user');

    return response;
  }

  getCurrentUser(): UserSession | null {
    if (this.currentUser) {
      return this.currentUser;
    }

    // Tentar recuperar do localStorage
    const stored = localStorage.getItem('klube_user');
    if (stored) {
      try {
        this.currentUser = JSON.parse(stored);
        return this.currentUser;
      } catch {
        localStorage.removeItem('klube_user');
      }
    }

    return null;
  }

  isAuthenticated(): boolean {
    return this.getCurrentUser() !== null;
  }

  async checkSession(): Promise<ApiResponse> {
    return apiClient.get('/auth-check.php');
  }
}

export const authService = new AuthService();
