# PLANEJAMENTO COMPLETO: SISTEMA DE LOJISTAS EM REACT

## 📋 ÍNDICE
1. [Visão Geral](#visao-geral)
2. [Arquitetura do Projeto](#arquitetura)
3. [Estrutura de Diretórios](#estrutura)
4. [Telas e Funcionalidades](#telas)
5. [Modelos de Dados](#modelos)
6. [APIs e Serviços](#apis)
7. [Fluxo de Autenticação](#autenticacao)
8. [Componentes Principais](#componentes)
9. [Estado Global](#estado)
10. [Cronograma de Implementação](#cronograma)

---

## 1. VISÃO GERAL <a name="visao-geral"></a>

### Objetivo
Refazer completamente o sistema de gerenciamento de lojistas (merchants) utilizando **React**, mantendo todas as funcionalidades atuais e melhorando a experiência do usuário.

### Escopo do Projeto
- **17 telas** de lojista convertidas para React
- **Reutilização** da página de login atual (PHP)
- **Backend** mantido (PHP + APIs existentes)
- **Frontend** totalmente novo em React

### Tecnologias
- **React 18**
- **React Router v6** (navegação)
- **Redux Toolkit** (gerenciamento de estado)
- **Axios** (requisições HTTP)
- **Tailwind CSS** (estilização)
- **Chart.js** (gráficos)
- **React Hook Form** (formulários)
- **Zod** (validação)

---

## 2. ARQUITETURA DO PROJETO <a name="arquitetura"></a>

### Diagrama de Arquitetura
```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (React)                          │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Pages      │  │  Components  │  │   Hooks      │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Context    │  │   Services   │  │   Utils      │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                         │                                    │
│                         ▼                                    │
├─────────────────────────────────────────────────────────────┤
│                    API LAYER (Axios)                         │
├─────────────────────────────────────────────────────────────┤
│                         │                                    │
│                         ▼                                    │
├─────────────────────────────────────────────────────────────┤
│                  BACKEND (PHP + MySQL)                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Controllers  │  │   Models     │  │  Database    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

### Princípios de Design
- **Component-Based**: Componentes reutilizáveis e modulares
- **Single Responsibility**: Cada componente/serviço tem uma responsabilidade única
- **DRY** (Don't Repeat Yourself): Evitar duplicação de código
- **Separation of Concerns**: Lógica de negócio separada da apresentação

---

## 3. ESTRUTURA DE DIRETÓRIOS <a name="estrutura"></a>

```
merchant-react-app/
├── public/
│   ├── index.html
│   ├── favicon.ico
│   └── assets/
│       ├── images/
│       └── icons/
│
├── src/
│   ├── components/
│   │   ├── common/              # Componentes reutilizáveis
│   │   │   ├── Button/
│   │   │   ├── Input/
│   │   │   ├── Modal/
│   │   │   ├── Toast/
│   │   │   ├── Table/
│   │   │   ├── Card/
│   │   │   ├── Spinner/
│   │   │   └── Badge/
│   │   │
│   │   ├── layout/              # Componentes de layout
│   │   │   ├── Sidebar/
│   │   │   │   ├── Sidebar.jsx
│   │   │   │   ├── SidebarItem.jsx
│   │   │   │   └── Sidebar.module.css
│   │   │   ├── Header/
│   │   │   │   ├── Header.jsx
│   │   │   │   ├── UserMenu.jsx
│   │   │   │   └── Header.module.css
│   │   │   └── MainLayout/
│   │   │       ├── MainLayout.jsx
│   │   │       └── MainLayout.module.css
│   │   │
│   │   ├── dashboard/           # Componentes do Dashboard
│   │   │   ├── SalesStats.jsx
│   │   │   ├── SalesChart.jsx
│   │   │   ├── RecentTransactions.jsx
│   │   │   └── PendingCommissions.jsx
│   │   │
│   │   ├── transactions/        # Componentes de Transações
│   │   │   ├── TransactionsList.jsx
│   │   │   ├── TransactionForm.jsx
│   │   │   ├── TransactionFilters.jsx
│   │   │   ├── TransactionCard.jsx
│   │   │   └── BatchUpload.jsx
│   │   │
│   │   ├── payments/            # Componentes de Pagamentos
│   │   │   ├── PaymentHistory.jsx
│   │   │   ├── PaymentRequest.jsx
│   │   │   ├── PIXPayment.jsx
│   │   │   ├── QRCodeDisplay.jsx
│   │   │   └── PaymentStatusBadge.jsx
│   │   │
│   │   ├── subscriptions/       # Componentes de Assinaturas
│   │   │   ├── SubscriptionCard.jsx
│   │   │   ├── PlanSelector.jsx
│   │   │   ├── PlanComparison.jsx
│   │   │   └── InvoiceHistory.jsx
│   │   │
│   │   ├── profile/             # Componentes de Perfil
│   │   │   ├── ProfileForm.jsx
│   │   │   ├── AddressForm.jsx
│   │   │   ├── ContactForm.jsx
│   │   │   └── LogoUpload.jsx
│   │   │
│   │   └── employees/           # Componentes de Funcionários
│   │       ├── EmployeesList.jsx
│   │       ├── EmployeeForm.jsx
│   │       ├── EmployeeModal.jsx
│   │       └── EmployeeCard.jsx
│   │
│   ├── pages/                   # Páginas da aplicação
│   │   ├── Dashboard.jsx
│   │   ├── Transactions/
│   │   │   ├── TransactionsPage.jsx
│   │   │   └── RegisterTransactionPage.jsx
│   │   ├── Payments/
│   │   │   ├── PaymentsPage.jsx
│   │   │   └── PaymentRequestPage.jsx
│   │   ├── Subscription/
│   │   │   └── SubscriptionPage.jsx
│   │   ├── Profile/
│   │   │   └── ProfilePage.jsx
│   │   └── Employees/
│   │       └── EmployeesPage.jsx
│   │
│   ├── hooks/                   # Custom hooks
│   │   ├── useAuth.js
│   │   ├── useStore.js
│   │   ├── useTransactions.js
│   │   ├── usePayments.js
│   │   ├── useSubscription.js
│   │   ├── useEmployees.js
│   │   ├── useFetch.js
│   │   └── useNotification.js
│   │
│   ├── context/                 # Context API
│   │   ├── AuthContext.jsx
│   │   ├── StoreContext.jsx
│   │   └── NotificationContext.jsx
│   │
│   ├── store/                   # Redux store
│   │   ├── index.js
│   │   └── slices/
│   │       ├── authSlice.js
│   │       ├── storeSlice.js
│   │       ├── transactionsSlice.js
│   │       ├── paymentsSlice.js
│   │       └── employeesSlice.js
│   │
│   ├── services/                # API services
│   │   ├── api.js               # Axios instance
│   │   ├── authService.js
│   │   ├── storeService.js
│   │   ├── transactionService.js
│   │   ├── paymentService.js
│   │   ├── subscriptionService.js
│   │   └── employeeService.js
│   │
│   ├── utils/                   # Utilitários
│   │   ├── formatters.js        # Formatação de datas, valores
│   │   ├── validators.js        # Validações
│   │   ├── constants.js         # Constantes
│   │   ├── helpers.js           # Funções auxiliares
│   │   └── errorHandler.js      # Tratamento de erros
│   │
│   ├── styles/                  # Estilos globais
│   │   ├── index.css
│   │   ├── tailwind.css
│   │   └── variables.css
│   │
│   ├── App.jsx                  # Componente raiz
│   ├── App.test.js
│   ├── index.js                 # Entry point
│   └── setupTests.js
│
├── .env                         # Variáveis de ambiente
├── .env.example
├── .gitignore
├── package.json
├── tailwind.config.js
├── postcss.config.js
└── README.md
```

---

## 4. TELAS E FUNCIONALIDADES <a name="telas"></a>

### 4.1 Dashboard (`/stores/dashboard`)
**Funcionalidades:**
- Estatísticas de vendas (total, cashback, transações)
- Comissões pendentes
- Gráfico de vendas mensais (últimos 6 meses)
- Últimas 5 transações
- Contagem de clientes afetados

**Componentes:**
- `SalesStats.jsx` - Cards com estatísticas
- `SalesChart.jsx` - Gráfico de linha (Chart.js)
- `RecentTransactions.jsx` - Tabela de transações
- `PendingCommissions.jsx` - Card de comissões

**APIs:**
- `GET /api/stores.php?action=dashboard`
- `GET /api/transactions.php?limit=5`
- `GET /api/commissions.php?status=pendente`

---

### 4.2 Registrar Transação (`/stores/register-transaction`)
**Funcionalidades:**
- Buscar cliente por telefone ou CPF
- Inserir valor da compra
- Descrição da transação
- Cálculo automático de cashback
- Confirmação visual

**Componentes:**
- `TransactionForm.jsx` - Formulário completo
- `CustomerSearch.jsx` - Busca de cliente
- `CashbackCalculator.jsx` - Preview de cálculo

**APIs:**
- `GET /api/store-client-search.php?q={phone/cpf}`
- `POST /api/transactions.php`

**Validações:**
- Telefone: formato brasileiro (11 dígitos)
- CPF: validação de dígitos verificadores
- Valor: mínimo R$ 1,00
- Cliente: deve existir no sistema

---

### 4.3 Transações (`/stores/transactions`)
**Funcionalidades:**
- Lista todas as transações da loja
- Filtros: data, status, cliente, valor
- Paginação (20 por página)
- Exportar para CSV
- Ver detalhes da transação

**Componentes:**
- `TransactionsList.jsx` - Tabela principal
- `TransactionFilters.jsx` - Filtros
- `TransactionDetails.jsx` - Modal de detalhes
- `Pagination.jsx` - Controle de paginação

**APIs:**
- `GET /api/transactions.php?page={n}&filters={json}`

---

### 4.4 Solicitações de Pagamento (`/stores/payment-history`)
**Funcionalidades:**
- Histórico de solicitações
- Status: pendente, aprovado, rejeitado, PIX aguardando
- Filtros por data e status
- Ver comprovante
- Ver QR Code PIX (se aplicável)

**Componentes:**
- `PaymentHistory.jsx` - Lista de pagamentos
- `PaymentStatusBadge.jsx` - Badge de status
- `QRCodeModal.jsx` - Exibição de QR Code

**APIs:**
- `GET /api/payments.php`
- `GET /api/payments.php?id={id}` - Detalhes

---

### 4.5 Solicitar Pagamento (`/stores/payment`)
**Funcionalidades:**
- Ver saldo disponível para saque
- Escolher valor (mínimo R$ 50,00)
- Selecionar método: PIX ou Transferência
- Adicionar observação

**Componentes:**
- `PaymentRequest.jsx` - Formulário
- `BalanceDisplay.jsx` - Saldo disponível

**APIs:**
- `GET /api/balance.php`
- `POST /api/payments.php`

---

### 4.6 Pagamento PIX (`/stores/payment-pix`)
**Funcionalidades:**
- Gerar QR Code PIX (AbacatePay, OpenPix, MercadoPago)
- Exibir código para copiar
- Status em tempo real (polling)
- Notificação quando pago

**Componentes:**
- `PIXPayment.jsx` - Componente principal
- `QRCodeDisplay.jsx` - QR Code + código
- `PaymentStatus.jsx` - Status do pagamento

**APIs:**
- `POST /api/abacatepay.php` - Gerar cobrança
- `GET /api/payments.php?id={id}` - Verificar status
- Webhook: `/api/abacatepay-webhook.php`

---

### 4.7 Assinaturas (`/stores/subscription`)
**Funcionalidades:**
- Ver plano atual
- Comparar planos (Básico, Profissional, Empresarial)
- Fazer upgrade/downgrade
- Resgatar código de plano
- Histórico de faturas

**Componentes:**
- `SubscriptionCard.jsx` - Plano atual
- `PlanComparison.jsx` - Tabela comparativa
- `PlanCodeForm.jsx` - Resgatar código
- `InvoiceHistory.jsx` - Lista de faturas

**APIs:**
- `GET /api/subscriptions.php`
- `POST /api/subscriptions.php?action=upgrade`
- `POST /api/subscriptions.php?action=redeem`

**Planos:**
| Plano | Preço Mensal | Preço Anual | Features |
|-------|-------------|-------------|----------|
| Básico | R$ 49,90 | R$ 499,00 | 100 transações/mês |
| Profissional | R$ 99,90 | R$ 999,00 | 500 transações/mês |
| Empresarial | R$ 199,90 | R$ 1.999,00 | Transações ilimitadas |

---

### 4.8 Perfil da Loja (`/stores/profile`)
**Funcionalidades:**
- Editar nome fantasia, razão social
- Atualizar logo
- Editar descrição e categoria
- Atualizar website
- Configurar porcentagem de cashback
- Gerenciar endereço e contatos

**Componentes:**
- `ProfileForm.jsx` - Dados básicos
- `LogoUpload.jsx` - Upload de logo
- `AddressForm.jsx` - Endereço
- `ContactForm.jsx` - Contatos

**APIs:**
- `GET /api/stores.php?id={store_id}`
- `PUT /api/stores.php?id={store_id}`
- `POST /api/upload.php` - Upload de logo

**Validações:**
- CNPJ: 14 dígitos, validação de DV
- Website: URL válida
- Cashback: entre 0.1% e 50%

---

### 4.9 Funcionários (`/stores/funcionarios`)
**Funcionalidades:**
- Listar funcionários da loja
- Adicionar novo funcionário
- Editar funcionário
- Desativar funcionário
- Definir subtipo (gerente, coordenador, financeiro, vendedor)

**Componentes:**
- `EmployeesList.jsx` - Tabela de funcionários
- `EmployeeForm.jsx` - Formulário (modal)
- `EmployeeCard.jsx` - Card do funcionário

**APIs:**
- `GET /api/employees.php`
- `POST /api/employees.php`
- `PUT /api/employees.php?id={id}`
- `DELETE /api/employees.php?id={id}`

**Subtipos:**
- Funcionário
- Gerente
- Coordenador
- Assistente
- Financeiro
- Vendedor

---

### 4.10 Detalhes da Loja (`/stores/details`)
**Funcionalidades:**
- Ver informações completas da loja
- Status de aprovação
- Data de cadastro
- Observações do admin

**Componentes:**
- `StoreDetails.jsx` - Exibição de dados

**APIs:**
- `GET /api/store_details.php`

---

## 5. MODELOS DE DADOS <a name="modelos"></a>

### 5.1 Store (Loja)
```typescript
interface Store {
  id: number;
  usuario_id: number;
  nome_fantasia: string;
  razao_social: string;
  cnpj: string;
  email: string;
  telefone: string;
  categoria: string;
  porcentagem_cashback: number;
  descricao: string;
  website: string;
  logo: string;
  status: 'pendente' | 'aprovado' | 'rejeitado';
  porcentagem_cliente: number;
  porcentagem_admin: number;
  cashback_ativo: boolean;
  data_cadastro: string;
  data_aprovacao: string | null;
}
```

### 5.2 Transaction (Transação)
```typescript
interface Transaction {
  id: number;
  usuario_id: number;
  loja_id: number;
  criado_por: number;
  valor_total: number;
  valor_cashback: number;
  valor_cliente: number;
  valor_admin: number;
  valor_loja: number;
  codigo_transacao: string;
  descricao: string;
  data_transacao: string;
  status: 'pendente' | 'aprovado' | 'cancelado' | 'pagamento_pendente';
  notificacao_enviada: boolean;

  // Relacionamentos (populados)
  cliente?: {
    nome: string;
    telefone: string;
    cpf: string;
  };
}
```

### 5.3 Payment (Pagamento)
```typescript
interface Payment {
  id: number;
  loja_id: number;
  criado_por: number;
  valor_total: number;
  metodo_pagamento: 'pix' | 'transferencia';
  numero_referencia: string;
  comprovante: string | null;
  observacao: string;
  observacao_admin: string | null;
  data_registro: string;
  data_aprovacao: string | null;
  status: 'pendente' | 'aprovado' | 'rejeitado' | 'pix_aguardando' | 'pix_expirado';

  // PIX fields
  pix_charge_id: string | null;
  pix_qr_code: string | null;
  pix_qr_code_image: string | null;
  pix_paid_at: string | null;

  // MercadoPago
  mp_payment_id: string | null;
  mp_qr_code: string | null;
  mp_qr_code_base64: string | null;
  mp_status: string | null;

  // OpenPix
  openpix_charge_id: string | null;
  openpix_qr_code: string | null;
  openpix_qr_code_image: string | null;
  openpix_status: string | null;
  openpix_paid_at: string | null;
}
```

### 5.4 Subscription (Assinatura)
```typescript
interface Subscription {
  id: number;
  tipo: 'loja' | 'membro';
  loja_id: number;
  user_id: number;
  plano_id: number;
  status: 'trial' | 'ativa' | 'inadimplente' | 'cancelada' | 'suspensa';
  ciclo: 'monthly' | 'yearly';
  trial_end: string | null;
  current_period_start: string;
  current_period_end: string;
  next_invoice_date: string;
  cancel_at: string | null;
  canceled_at: string | null;
  gateway: 'abacate' | 'stripe';
  gateway_customer_id: string;
  gateway_subscription_id: string;
  created_at: string;
  updated_at: string;

  // Relacionamento
  plano?: Plan;
}
```

### 5.5 Plan (Plano)
```typescript
interface Plan {
  id: number;
  nome: string;
  slug: string;
  descricao: string;
  preco_mensal: number;
  preco_anual: number;
  moeda: string;
  trial_dias: number;
  recorrencia: 'monthly' | 'yearly' | 'both';
  features_json: string; // JSON string
  ativo: boolean;
  created_at: string;
  updated_at: string;

  // Parsed features
  features?: string[];
}
```

### 5.6 Employee (Funcionário)
```typescript
interface Employee {
  id: number;
  nome: string;
  email: string;
  telefone: string;
  cpf: string;
  tipo: 'funcionario';
  status: 'ativo' | 'inativo' | 'bloqueado';
  loja_vinculada_id: number;
  subtipo_funcionario: 'funcionario' | 'gerente' | 'coordenador' |
                       'assistente' | 'financeiro' | 'vendedor';
  data_criacao: string;
  ultimo_login: string | null;
}
```

---

## 6. APIS E SERVIÇOS <a name="apis"></a>

### 6.1 API Base Configuration
```javascript
// services/api.js
import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'https://klubecash.com/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Para enviar cookies
});

// Interceptor para adicionar JWT token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('jwt_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Interceptor para tratar erros
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expirado
      localStorage.removeItem('jwt_token');
      window.location.href = '/views/auth/login.php';
    }
    return Promise.reject(error);
  }
);

export default api;
```

### 6.2 Services

#### authService.js
```javascript
import api from './api';

export const authService = {
  // Verificar se está autenticado
  checkAuth: async () => {
    const response = await api.get('/validate-token.php');
    return response.data;
  },

  // Obter dados do usuário pelo token
  getUserByToken: async () => {
    const response = await api.get('/get-user-by-token.php');
    return response.data;
  },

  // Logout
  logout: async () => {
    localStorage.removeItem('jwt_token');
    window.location.href = '/views/auth/login.php';
  },
};
```

#### storeService.js
```javascript
import api from './api';

export const storeService = {
  // Obter ID da loja atual
  getStoreId: async () => {
    const response = await api.get('/get-store-id.php');
    return response.data.store_id;
  },

  // Obter dados da loja
  getStoreData: async (storeId) => {
    const response = await api.get(`/stores.php?id=${storeId}`);
    return response.data;
  },

  // Dashboard stats
  getDashboardData: async (storeId) => {
    const response = await api.get(`/stores.php?action=dashboard&id=${storeId}`);
    return response.data;
  },

  // Atualizar loja
  updateStore: async (storeId, data) => {
    const response = await api.put(`/stores.php?id=${storeId}`, data);
    return response.data;
  },

  // Upload logo
  uploadLogo: async (storeId, file) => {
    const formData = new FormData();
    formData.append('logo', file);
    formData.append('store_id', storeId);

    const response = await api.post('/upload.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  },
};
```

#### transactionService.js
```javascript
import api from './api';

export const transactionService = {
  // Listar transações
  getTransactions: async (storeId, filters = {}, page = 1, limit = 20) => {
    const params = {
      loja_id: storeId,
      page,
      limit,
      ...filters,
    };
    const response = await api.get('/transactions.php', { params });
    return response.data;
  },

  // Criar transação
  createTransaction: async (data) => {
    const response = await api.post('/transactions.php', data);
    return response.data;
  },

  // Obter detalhes
  getTransactionById: async (id) => {
    const response = await api.get(`/transactions.php?id=${id}`);
    return response.data;
  },

  // Buscar cliente
  searchCustomer: async (query) => {
    const response = await api.get(`/store-client-search.php?q=${query}`);
    return response.data;
  },
};
```

#### paymentService.js
```javascript
import api from './api';

export const paymentService = {
  // Listar pagamentos
  getPayments: async (storeId) => {
    const response = await api.get('/payments.php', {
      params: { loja_id: storeId },
    });
    return response.data;
  },

  // Solicitar pagamento
  requestPayment: async (data) => {
    const response = await api.post('/payments.php', data);
    return response.data;
  },

  // Gerar PIX (AbacatePay)
  generatePIX: async (paymentId, amount) => {
    const response = await api.post('/abacatepay.php', {
      payment_id: paymentId,
      amount,
    });
    return response.data;
  },

  // Verificar status do pagamento PIX
  checkPaymentStatus: async (paymentId) => {
    const response = await api.get(`/payments.php?id=${paymentId}`);
    return response.data;
  },

  // Obter saldo
  getBalance: async (storeId) => {
    const response = await api.get(`/balance.php?loja_id=${storeId}`);
    return response.data;
  },
};
```

#### subscriptionService.js
```javascript
import api from './api';

export const subscriptionService = {
  // Obter assinatura atual
  getCurrentSubscription: async (storeId) => {
    const response = await api.get(`/subscriptions.php?loja_id=${storeId}`);
    return response.data;
  },

  // Listar planos disponíveis
  getPlans: async () => {
    const response = await api.get('/subscriptions.php?action=plans');
    return response.data;
  },

  // Fazer upgrade
  upgradePlan: async (subscriptionId, planId, ciclo) => {
    const response = await api.post('/subscriptions.php?action=upgrade', {
      subscription_id: subscriptionId,
      plan_id: planId,
      ciclo,
    });
    return response.data;
  },

  // Resgatar código
  redeemCode: async (code) => {
    const response = await api.post('/subscriptions.php?action=redeem', {
      code,
    });
    return response.data;
  },

  // Histórico de faturas
  getInvoices: async (subscriptionId) => {
    const response = await api.get(`/subscriptions.php?action=invoices&id=${subscriptionId}`);
    return response.data;
  },
};
```

#### employeeService.js
```javascript
import api from './api';

export const employeeService = {
  // Listar funcionários
  getEmployees: async (storeId) => {
    const response = await api.get('/employees.php', {
      params: { loja_id: storeId },
    });
    return response.data;
  },

  // Criar funcionário
  createEmployee: async (data) => {
    const response = await api.post('/employees.php', data);
    return response.data;
  },

  // Atualizar funcionário
  updateEmployee: async (id, data) => {
    const response = await api.put(`/employees.php?id=${id}`, data);
    return response.data;
  },

  // Excluir/desativar funcionário
  deleteEmployee: async (id) => {
    const response = await api.delete(`/employees.php?id=${id}`);
    return response.data;
  },

  // Obter detalhes
  getEmployeeById: async (id) => {
    const response = await api.get(`/employees.php?id=${id}`);
    return response.data;
  },
};
```

---

## 7. FLUXO DE AUTENTICAÇÃO <a name="autenticacao"></a>

### 7.1 Login (Mantido em PHP)
1. Usuário acessa `/views/auth/login.php`
2. Preenche e-mail e senha
3. PHP valida credenciais
4. Se válido:
   - Cria sessão PHP
   - Gera JWT token (24h)
   - Define cookie `jwt_token`
   - Redireciona para React app

### 7.2 Verificação no React
```javascript
// App.jsx
useEffect(() => {
  const checkAuth = async () => {
    try {
      const token = localStorage.getItem('jwt_token');
      if (!token) {
        window.location.href = '/views/auth/login.php';
        return;
      }

      const userData = await authService.getUserByToken();
      setUser(userData);

      // Verificar se é lojista
      if (userData.tipo !== 'loja' && userData.tipo !== 'funcionario') {
        window.location.href = '/views/auth/login.php?error=unauthorized';
        return;
      }

      // Obter store_id
      const storeId = await storeService.getStoreId();
      setStoreId(storeId);

    } catch (error) {
      window.location.href = '/views/auth/login.php';
    }
  };

  checkAuth();
}, []);
```

### 7.3 Rotas Protegidas
```javascript
// components/PrivateRoute.jsx
import { Navigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

const PrivateRoute = ({ children }) => {
  const { user, loading } = useAuth();

  if (loading) return <Spinner />;

  if (!user) {
    window.location.href = '/views/auth/login.php';
    return null;
  }

  if (user.tipo !== 'loja' && user.tipo !== 'funcionario') {
    return <Navigate to="/unauthorized" />;
  }

  return children;
};
```

---

## 8. COMPONENTES PRINCIPAIS <a name="componentes"></a>

### 8.1 Layout Components

#### Sidebar.jsx
```javascript
import { NavLink } from 'react-router-dom';
import {
  HomeIcon,
  DocumentIcon,
  CreditCardIcon,
  UserGroupIcon,
  CogIcon
} from '@heroicons/react/outline';

const menuItems = [
  { name: 'Dashboard', path: '/stores/dashboard', icon: HomeIcon },
  { name: 'Transações', path: '/stores/transactions', icon: DocumentIcon },
  { name: 'Pagamentos', path: '/stores/payments', icon: CreditCardIcon },
  { name: 'Funcionários', path: '/stores/employees', icon: UserGroupIcon },
  { name: 'Perfil', path: '/stores/profile', icon: CogIcon },
];

export default function Sidebar() {
  return (
    <aside className="sidebar">
      <div className="logo">
        <img src="/assets/images/logo.png" alt="Klube Cash" />
      </div>

      <nav className="menu">
        {menuItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            className={({ isActive }) =>
              `menu-item ${isActive ? 'active' : ''}`
            }
          >
            <item.icon className="icon" />
            <span>{item.name}</span>
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}
```

#### Header.jsx
```javascript
import { useAuth } from '../../hooks/useAuth';
import UserMenu from './UserMenu';

export default function Header() {
  const { user } = useAuth();

  return (
    <header className="header">
      <div className="header-left">
        <h1>Bem-vindo, {user?.nome}</h1>
      </div>

      <div className="header-right">
        <UserMenu user={user} />
      </div>
    </header>
  );
}
```

### 8.2 Dashboard Components

#### SalesStats.jsx
```javascript
export default function SalesStats({ stats }) {
  return (
    <div className="stats-grid">
      <StatCard
        title="Vendas Totais"
        value={formatCurrency(stats.total_vendas)}
        icon={<CurrencyDollarIcon />}
        color="blue"
      />
      <StatCard
        title="Cashback Distribuído"
        value={formatCurrency(stats.total_cashback)}
        icon={<GiftIcon />}
        color="green"
      />
      <StatCard
        title="Transações"
        value={stats.total_transacoes}
        icon={<DocumentIcon />}
        color="purple"
      />
      <StatCard
        title="Comissões Pendentes"
        value={formatCurrency(stats.comissoes_pendentes)}
        icon={<ClockIcon />}
        color="orange"
      />
    </div>
  );
}
```

#### SalesChart.jsx
```javascript
import { Line } from 'react-chartjs-2';

export default function SalesChart({ data }) {
  const chartData = {
    labels: data.map(d => d.mes),
    datasets: [{
      label: 'Vendas',
      data: data.map(d => d.valor),
      borderColor: '#FF7A00',
      backgroundColor: 'rgba(255, 122, 0, 0.1)',
      tension: 0.4,
    }],
  };

  return (
    <div className="chart-container">
      <h3>Vendas dos Últimos 6 Meses</h3>
      <Line data={chartData} options={chartOptions} />
    </div>
  );
}
```

### 8.3 Transaction Components

#### TransactionForm.jsx
```javascript
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';

const schema = z.object({
  customer_phone: z.string().regex(/^\d{11}$/, 'Telefone inválido'),
  customer_cpf: z.string().regex(/^\d{11}$/, 'CPF inválido'),
  amount: z.number().min(1, 'Valor mínimo R$ 1,00'),
  description: z.string().min(3, 'Descrição muito curta'),
});

export default function TransactionForm({ onSubmit }) {
  const { register, handleSubmit, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
  });

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Input
        label="Telefone do Cliente"
        {...register('customer_phone')}
        error={errors.customer_phone?.message}
      />

      <Input
        label="CPF"
        {...register('customer_cpf')}
        error={errors.customer_cpf?.message}
      />

      <Input
        label="Valor (R$)"
        type="number"
        step="0.01"
        {...register('amount', { valueAsNumber: true })}
        error={errors.amount?.message}
      />

      <Textarea
        label="Descrição"
        {...register('description')}
        error={errors.description?.message}
      />

      <Button type="submit">Registrar Transação</Button>
    </form>
  );
}
```

---

## 9. ESTADO GLOBAL <a name="estado"></a>

### Redux Store Setup

```javascript
// store/index.js
import { configureStore } from '@reduxjs/toolkit';
import authReducer from './slices/authSlice';
import storeReducer from './slices/storeSlice';
import transactionsReducer from './slices/transactionsSlice';
import paymentsReducer from './slices/paymentsSlice';
import employeesReducer from './slices/employeesSlice';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    store: storeReducer,
    transactions: transactionsReducer,
    payments: paymentsReducer,
    employees: employeesReducer,
  },
});
```

### Auth Slice
```javascript
// store/slices/authSlice.js
import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { authService } from '../../services/authService';

export const fetchUser = createAsyncThunk(
  'auth/fetchUser',
  async () => {
    const response = await authService.getUserByToken();
    return response;
  }
);

const authSlice = createSlice({
  name: 'auth',
  initialState: {
    user: null,
    loading: false,
    error: null,
  },
  reducers: {
    logout: (state) => {
      state.user = null;
      authService.logout();
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchUser.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchUser.fulfilled, (state, action) => {
        state.user = action.payload;
        state.loading = false;
      })
      .addCase(fetchUser.rejected, (state, action) => {
        state.error = action.error.message;
        state.loading = false;
      });
  },
});

export const { logout } = authSlice.actions;
export default authSlice.reducer;
```

---

## 10. CRONOGRAMA DE IMPLEMENTAÇÃO <a name="cronograma"></a>

### Fase 1: Configuração Base (1-2 dias)
- [x] Criar projeto React com Create React App
- [x] Instalar dependências (Redux, Router, Axios, Tailwind)
- [x] Configurar estrutura de pastas
- [x] Configurar Tailwind CSS
- [x] Criar variáveis de ambiente

### Fase 2: Autenticação e Layout (2-3 dias)
- [ ] Implementar sistema de autenticação
- [ ] Criar rotas protegidas
- [ ] Desenvolver componentes de layout (Sidebar, Header)
- [ ] Implementar sistema de notificações (Toast)

### Fase 3: Dashboard (2 dias)
- [ ] Criar página do Dashboard
- [ ] Implementar componente de estatísticas
- [ ] Adicionar gráfico de vendas (Chart.js)
- [ ] Criar lista de transações recentes

### Fase 4: Transações (3-4 dias)
- [ ] Criar formulário de registro de transação
- [ ] Implementar busca de clientes
- [ ] Criar lista de transações com filtros
- [ ] Adicionar paginação
- [ ] Implementar detalhes da transação

### Fase 5: Pagamentos (3 dias)
- [ ] Criar histórico de pagamentos
- [ ] Implementar solicitação de pagamento
- [ ] Integrar geração de QR Code PIX
- [ ] Adicionar polling para status de pagamento
- [ ] Criar visualização de comprovantes

### Fase 6: Assinaturas (2 dias)
- [ ] Criar página de assinatura
- [ ] Implementar comparação de planos
- [ ] Adicionar upgrade/downgrade
- [ ] Criar resgate de código de plano

### Fase 7: Perfil da Loja (2 dias)
- [ ] Criar formulário de edição de perfil
- [ ] Implementar upload de logo
- [ ] Adicionar formulário de endereço
- [ ] Criar gerenciamento de contatos

### Fase 8: Funcionários (2 dias)
- [ ] Criar lista de funcionários
- [ ] Implementar CRUD de funcionários
- [ ] Adicionar seleção de subtipo

### Fase 9: Testes e Otimizações (2-3 dias)
- [ ] Testes unitários (Jest + React Testing Library)
- [ ] Testes de integração
- [ ] Otimização de performance
- [ ] Code splitting
- [ ] Lazy loading

### Fase 10: Deploy (1 dia)
- [ ] Build de produção
- [ ] Configurar servidor
- [ ] Deploy da aplicação
- [ ] Testes finais

**Total Estimado: 20-25 dias úteis**

---

## 11. VARIÁVEIS DE AMBIENTE

### .env.example
```env
# API
REACT_APP_API_URL=https://klubecash.com/api
REACT_APP_SITE_URL=https://klubecash.com

# Features
REACT_APP_ENABLE_GOOGLE_LOGIN=true
REACT_APP_ENABLE_SENAT=true

# Pagination
REACT_APP_DEFAULT_PAGE_SIZE=20

# Upload
REACT_APP_MAX_FILE_SIZE=5242880
REACT_APP_ALLOWED_FILE_TYPES=image/jpeg,image/png,image/webp

# Payment Gateways
REACT_APP_ABACATEPAY_ENABLED=true
REACT_APP_OPENPIX_ENABLED=true
REACT_APP_MERCADOPAGO_ENABLED=true
```

---

## 12. DEPENDÊNCIAS

### package.json
```json
{
  "name": "klubecash-merchant-app",
  "version": "1.0.0",
  "dependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
    "react-router-dom": "^6.10.0",
    "@reduxjs/toolkit": "^1.9.5",
    "react-redux": "^8.0.5",
    "axios": "^1.4.0",
    "react-hook-form": "^7.43.9",
    "zod": "^3.21.4",
    "@hookform/resolvers": "^3.1.0",
    "chart.js": "^4.3.0",
    "react-chartjs-2": "^5.2.0",
    "date-fns": "^2.30.0",
    "qrcode.react": "^3.1.0",
    "@heroicons/react": "^2.0.17",
    "clsx": "^1.2.1"
  },
  "devDependencies": {
    "tailwindcss": "^3.3.2",
    "autoprefixer": "^10.4.14",
    "postcss": "^8.4.23",
    "@testing-library/react": "^14.0.0",
    "@testing-library/jest-dom": "^5.16.5",
    "jest": "^29.5.0"
  }
}
```

---

## 13. PRÓXIMOS PASSOS

1. **Aprovar este planejamento**
2. **Criar projeto React**
3. **Implementar fase por fase**
4. **Testar cada funcionalidade**
5. **Deploy gradual**

---

## 14. OBSERVAÇÕES IMPORTANTES

### Manter Compatibilidade
- Login continua em PHP
- Backend não será alterado
- APIs existentes serão utilizadas
- Sessões PHP mantidas para compatibilidade

### Melhorias Futuras
- Notificações em tempo real (WebSockets)
- PWA (Progressive Web App)
- Dark mode
- Multi-idioma (i18n)
- Analytics integrado

---

**FIM DO PLANEJAMENTO**
