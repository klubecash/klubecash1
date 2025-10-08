import { apiClient, ApiResponse, DashboardKPI, Transaction } from '@/lib/api';

export interface DashboardData {
  kpi: DashboardKPI;
  recentTransactions: Transaction[];
}

export const dashboardService = {
  // Get dashboard KPI data
  async getKPIData(): Promise<ApiResponse<DashboardKPI>> {
    return apiClient.get<DashboardKPI>('/dashboard.php', { endpoint: 'kpi' });
  },

  // Get recent transactions
  async getRecentTransactions(limit: number = 5): Promise<ApiResponse<Transaction[]>> {
    return apiClient.get<Transaction[]>('/transactions.php', {
      page: 1,
      limit
    });
  },

  // Get all dashboard data in one call (recommended)
  async getDashboardData(): Promise<ApiResponse<DashboardData>> {
    return apiClient.get<DashboardData>('/dashboard.php');
  },

  // Get store details
  async getStoreDetails(storeId: number): Promise<ApiResponse> {
    return apiClient.get(`/store_details.php`, { loja_id: storeId });
  }
};
