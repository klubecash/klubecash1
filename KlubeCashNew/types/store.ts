export type ApiResponse<T> = {
  status: "success" | "error";
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  requestId: string;
};

export type StoreContext = {
  dataState: "ready" | "empty";
  generatedAt: string;
  store: {
    id: number;
    name: string;
    status: string;
    logoUrl: string | null;
    customerCashbackPercentage: number;
    cashbackEnabled: boolean;
    mvp: boolean;
    financialModel: "subscription_cashback";
  };
  user: {
    id?: number;
    name: string;
    type: "loja" | "funcionario";
    subtype: string | null;
    avatarInitial: string;
  };
  permissions: {
    manageEmployees: boolean;
    deactivateEmployees: boolean;
    assignSeller?: boolean;
  };
  subscription: {
    active: boolean;
    salesBlocked?: boolean;
    reason?: string | null;
    status: string | null;
    planName: string | null;
  };
  csrfToken: string;
  activeStoreId?: number;
  networkId?: number | null;
  networkName?: string | null;
  canViewNetwork?: boolean;
  stores?: Array<{ id: number; name: string; role: string; networkId: number | null }>;
};

export type DashboardData = {
  dataState: "ready" | "empty";
  generatedAt: string;
  summary: {
    salesCount: number;
    grossAmountCents: number;
    cashbackGrantedCents: number;
    balanceUsedCents: number;
    outsideBalanceCents: number;
    averageTicketCents: number;
    cancelledCount: number;
    customersCount: number;
    lastTransactionAt: string | null;
  };
  recentTransactions: Array<{
    id: number;
    code: string;
    customerName: string;
    grossAmountCents: number;
    balanceUsedCents: number;
    paidAmountCents: number;
    cashbackGrantedCents: number;
    status: string;
    occurredAt: string;
    sellerName?: string;
    recordedByName?: string;
    storeName?: string;
  }>;
  monthlySales: Array<{
    month: string;
    salesCount: number;
    grossAmountCents: number;
  }>;
};

export type Pagination = {
  page: number;
  pageSize: number;
  totalItems: number;
  totalPages: number;
};
