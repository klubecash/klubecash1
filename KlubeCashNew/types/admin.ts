export type ApiResponse<T> = {
  status: "success" | "error";
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  requestId: string;
  generatedAt: string;
  dataState?: "ready" | "empty";
};

export type DataState = {
  dataState: "ready" | "empty";
  generatedAt: string;
};

export type Pagination = {
  page: number;
  pageSize: number;
  totalItems: number;
  totalPages: number;
};

export type PageData<T> = DataState & {
  items: T[];
  pagination: Pagination;
};

export type AdminContext = DataState & {
  user: { id: number; name: string; email: string; avatarInitial: string };
  permissions: Record<string, boolean>;
  financialModel: "subscription_cashback";
  csrfToken: string;
};

export type DashboardData = DataState & {
  summary: {
    customers: number;
    approvedStores: number;
    pendingStores: number;
    salesCount: number;
    grossAmountCents: number;
    cashbackAmountCents: number;
    currentSalesCount: number;
    currentGrossAmountCents: number;
    currentCashbackAmountCents: number;
    legacySalesCount: number;
    legacyGrossAmountCents: number;
    legacyCashbackAmountCents: number;
    activeSubscriptions: number;
    pendingLegacyItems: number;
  };
  recentTransactions: TransactionItem[];
  pendingStores: Array<{
    id: number;
    name: string;
    cnpj: string;
    category: string;
    registeredAt: string;
  }>;
  monthly: Array<{
    month: string;
    salesCount: number;
    grossAmountCents: number;
    cashbackAmountCents: number;
  }>;
};

export type UserItem = {
  id: number;
  name: string;
  email: string;
  phone: string;
  status: string;
  type: string;
  customerType: string | null;
  linkedStoreId: number | null;
  linkedStoreName: string | null;
  employeeSubtype: string | null;
  createdAt: string;
  lastLoginAt: string | null;
  updatedAt: string;
};

export type StoreItem = {
  id: number;
  name: string;
  legalName: string;
  cnpj: string;
  email: string;
  phone: string;
  category: string;
  status: string;
  notes: string | null;
  customerCashbackPercentage: number;
  cashbackEnabled: boolean;
  ownerName: string;
  transactionsCount: number;
  grossAmountCents: number;
  registeredAt: string;
  approvedAt: string | null;
  updatedAt: string;
  description?: string;
  website?: string;
  employeesCount?: number;
  owner?: { id: number; name: string; email: string };
  address?: {
    postalCode: string;
    street: string;
    number: string;
    complement: string;
    district: string;
    city: string;
    state: string;
  };
  employees?: Array<{ id: number; name: string; email: string; status: string; subtype: string }>;
  subscription?: { id: number; planName: string; status: string; cycle: string; periodEnd: string | null } | null;
};

export type TransactionItem = {
  id: number;
  code: string;
  customerName: string;
  customerEmail?: string;
  storeName: string;
  grossAmountCents: number;
  balanceUsedCents: number;
  paidAmountCents: number;
  cashbackAmountCents: number;
  adminAmountCents?: number;
  storeAmountCents?: number;
  status: string;
  financialModel: string;
  occurredAt: string;
  description?: string;
  movements?: Array<{
    id: number;
    type: string;
    amountCents: number;
    previousCents: number;
    currentCents: number;
    description: string;
    occurredAt: string;
  }>;
};

export type FinanceData = DataState & {
  summary: {
    commissionPaidCents: number;
    commissionPendingCents: number;
    balancePaidCents: number;
    balancePendingCents: number;
  };
  commissionPayments: FinanceItem[];
  balancePayments: FinanceItem[];
  pagination: Pagination;
};

export type FinanceItem = {
  id: number;
  kind: "commission" | "balance_refund";
  storeId: number;
  storeName: string;
  amountCents: number;
  method: string;
  reference: string | null;
  status: string;
  transactionCount?: number;
  notes: string | null;
  adminNotes?: string | null;
  reviewRequired?: boolean;
  reviewReason?: string | null;
  createdAt: string;
  processedAt: string | null;
};

export type SettingsData = DataState & {
  cashback: {
    customerPercentage: number;
    legacyAdminPercentage: number;
    legacyStorePercentage: number;
  };
  balance: {
    enabled: boolean;
    minimumUseCents: number;
    maximumPurchasePercentage: number;
    lowBalanceNotification: boolean;
    lowBalanceThresholdCents: number;
  };
  notifications: {
    newTransactionEmail: boolean;
    approvedPaymentEmail: boolean;
    availableBalanceEmail: boolean;
    lowBalanceEmail: boolean;
    expiredBalanceEmail: boolean;
  };
};

export type SubscriptionItem = {
  id: number;
  storeId: number;
  storeName: string;
  storeEmail?: string;
  planName: string;
  planSlug: string;
  status: string;
  cycle: string;
  trialEnd: string | null;
  periodStart: string;
  periodEnd: string;
  nextInvoiceDate: string | null;
  createdAt: string;
  updatedAt: string;
  invoices?: Array<{
    id: number;
    number: string;
    amountCents: number;
    status: string;
    dueDate: string;
    paidAt: string | null;
    paymentMethod: string | null;
    createdAt: string;
  }>;
};

export type PlanItem = {
  id: number;
  name: string;
  slug: string;
  code: string | null;
  description: string | null;
  monthlyPriceCents: number;
  annualPriceCents: number;
  trialDays: number;
  recurrence: string;
  features: string[];
  active: boolean;
  updatedAt: string;
};

export type CampaignItem = {
  id: number;
  title: string;
  subject: string;
  status: string;
  requiresReview: boolean;
  totalRecipients: number;
  sent: number;
  failed: number;
  scheduledAt: string | null;
  createdAt: string;
  updatedAt: string;
};

export type TemplateItem = {
  id: number;
  name: string;
  subject: string;
  html: string;
  type: string;
  active: boolean;
  updatedAt: string;
};

export type AuditItem = {
  id: number;
  action: string;
  entityType: string;
  entityId: string | null;
  result: string;
  requestId: string;
  actorName: string;
  createdAt: string;
};
