// API Configuration
export const API_BASE_URL = process.env.REACT_APP_API_URL || 'https://klubecash.com/api';
export const SITE_URL = process.env.REACT_APP_SITE_URL || 'https://klubecash.com';

// Pagination
export const DEFAULT_PAGE_SIZE = parseInt(process.env.REACT_APP_DEFAULT_PAGE_SIZE || '20');

// File Upload
export const MAX_FILE_SIZE = parseInt(process.env.REACT_APP_MAX_FILE_SIZE || '5242880'); // 5MB
export const ALLOWED_FILE_TYPES = (process.env.REACT_APP_ALLOWED_FILE_TYPES || 'image/jpeg,image/png,image/webp').split(',');

// Transaction Status
export const TRANSACTION_STATUS = {
  PENDING: 'pendente',
  APPROVED: 'aprovado',
  CANCELED: 'cancelado',
  PAYMENT_PENDING: 'pagamento_pendente',
};

export const TRANSACTION_STATUS_LABELS = {
  [TRANSACTION_STATUS.PENDING]: 'Pendente',
  [TRANSACTION_STATUS.APPROVED]: 'Aprovado',
  [TRANSACTION_STATUS.CANCELED]: 'Cancelado',
  [TRANSACTION_STATUS.PAYMENT_PENDING]: 'Pagamento Pendente',
};

// Payment Status
export const PAYMENT_STATUS = {
  PENDING: 'pendente',
  APPROVED: 'aprovado',
  REJECTED: 'rejeitado',
  PIX_WAITING: 'pix_aguardando',
  PIX_EXPIRED: 'pix_expirado',
};

export const PAYMENT_STATUS_LABELS = {
  [PAYMENT_STATUS.PENDING]: 'Pendente',
  [PAYMENT_STATUS.APPROVED]: 'Aprovado',
  [PAYMENT_STATUS.REJECTED]: 'Rejeitado',
  [PAYMENT_STATUS.PIX_WAITING]: 'Aguardando PIX',
  [PAYMENT_STATUS.PIX_EXPIRED]: 'PIX Expirado',
};

// Subscription Status
export const SUBSCRIPTION_STATUS = {
  TRIAL: 'trial',
  ACTIVE: 'ativa',
  OVERDUE: 'inadimplente',
  CANCELED: 'cancelada',
  SUSPENDED: 'suspensa',
};

export const SUBSCRIPTION_STATUS_LABELS = {
  [SUBSCRIPTION_STATUS.TRIAL]: 'Trial',
  [SUBSCRIPTION_STATUS.ACTIVE]: 'Ativa',
  [SUBSCRIPTION_STATUS.OVERDUE]: 'Inadimplente',
  [SUBSCRIPTION_STATUS.CANCELED]: 'Cancelada',
  [SUBSCRIPTION_STATUS.SUSPENDED]: 'Suspensa',
};

// Employee Subtypes
export const EMPLOYEE_SUBTYPES = {
  EMPLOYEE: 'funcionario',
  MANAGER: 'gerente',
  COORDINATOR: 'coordenador',
  ASSISTANT: 'assistente',
  FINANCIAL: 'financeiro',
  SELLER: 'vendedor',
};

export const EMPLOYEE_SUBTYPES_LABELS = {
  [EMPLOYEE_SUBTYPES.EMPLOYEE]: 'Funcionário',
  [EMPLOYEE_SUBTYPES.MANAGER]: 'Gerente',
  [EMPLOYEE_SUBTYPES.COORDINATOR]: 'Coordenador',
  [EMPLOYEE_SUBTYPES.ASSISTANT]: 'Assistente',
  [EMPLOYEE_SUBTYPES.FINANCIAL]: 'Financeiro',
  [EMPLOYEE_SUBTYPES.SELLER]: 'Vendedor',
};

// Payment Methods
export const PAYMENT_METHODS = {
  PIX: 'pix',
  BANK_TRANSFER: 'transferencia',
};

export const PAYMENT_METHODS_LABELS = {
  [PAYMENT_METHODS.PIX]: 'PIX',
  [PAYMENT_METHODS.BANK_TRANSFER]: 'Transferência Bancária',
};

// Store Status
export const STORE_STATUS = {
  PENDING: 'pendente',
  APPROVED: 'aprovado',
  REJECTED: 'rejeitado',
};

export const STORE_STATUS_LABELS = {
  [STORE_STATUS.PENDING]: 'Pendente',
  [STORE_STATUS.APPROVED]: 'Aprovado',
  [STORE_STATUS.REJECTED]: 'Rejeitado',
};

// Routes
export const ROUTES = {
  DASHBOARD: '/stores/dashboard',
  TRANSACTIONS: '/stores/transactions',
  REGISTER_TRANSACTION: '/stores/register-transaction',
  PAYMENTS: '/stores/payments',
  REQUEST_PAYMENT: '/stores/request-payment',
  PAYMENT_PIX: '/stores/payment-pix',
  SUBSCRIPTION: '/stores/subscription',
  PROFILE: '/stores/profile',
  EMPLOYEES: '/stores/employees',
  DETAILS: '/stores/details',
};

// Notification Types
export const NOTIFICATION_TYPES = {
  SUCCESS: 'success',
  ERROR: 'error',
  WARNING: 'warning',
  INFO: 'info',
};
