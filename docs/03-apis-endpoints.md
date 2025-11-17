# 03 - APIs e Endpoints

## 📋 Índice
- [Visão Geral](#visão-geral)
- [Autenticação](#autenticação)
- [Usuários](#usuários)
- [Lojas](#lojas)
- [Transações](#transações)
- [Pagamentos](#pagamentos)
- [Assinaturas](#assinaturas)
- [Comissões](#comissões)
- [Funcionários](#funcionários)
- [Webhooks](#webhooks)
- [Códigos de Status](#códigos-de-status)

---

## 🌐 Visão Geral

### Base URL
```
Produção: https://klubecash.com
Desenvolvimento: http://localhost:8000
```

### Formato de Requisição/Resposta
- **Content-Type**: `application/json`
- **Charset**: UTF-8
- **Formato de data**: ISO 8601 (`2025-11-17T10:30:00Z`)

### Autenticação
A maioria dos endpoints requer autenticação via JWT token no header:
```http
Authorization: Bearer {jwt_token}
```

### Estrutura Padrão de Resposta

**Sucesso**:
```json
{
  "success": true,
  "data": { /* dados retornados */ },
  "message": "Operação realizada com sucesso"
}
```

**Erro**:
```json
{
  "success": false,
  "error": "Mensagem de erro",
  "code": "ERROR_CODE",
  "details": { /* detalhes adicionais */ }
}
```

---

## 🔐 Autenticação

### 1. Login

Autentica usuário e retorna JWT token.

**Endpoint**: `POST /api/auth/login.php`

**Autenticação**: Não requerida

**Parâmetros**:
```json
{
  "identifier": "123.456.789-00",  // CPF ou email
  "password": "senha123"
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 123,
      "name": "João Silva",
      "email": "joao@email.com",
      "cpf": "123.456.789-00",
      "type": "user",
      "wallet_balance": 150.00
    }
  },
  "message": "Login realizado com sucesso"
}
```

**Erros**:
- `401`: Credenciais inválidas
- `404`: Usuário não encontrado
- `403`: Usuário bloqueado

**Exemplo cURL**:
```bash
curl -X POST https://klubecash.com/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "joao@email.com",
    "password": "senha123"
  }'
```

---

### 2. Registro

Cria nova conta de usuário.

**Endpoint**: `POST /api/auth/register.php`

**Autenticação**: Não requerida

**Parâmetros**:
```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "cpf": "123.456.789-00",
  "phone": "(11) 99999-9999",
  "password": "senha123",
  "password_confirmation": "senha123",
  "referral_code": "ABC123" // Opcional
}
```

**Resposta Sucesso** (201):
```json
{
  "success": true,
  "data": {
    "user_id": 123,
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  },
  "message": "Cadastro realizado com sucesso"
}
```

**Erros**:
- `400`: Dados inválidos
- `409`: CPF ou email já cadastrado

---

### 3. Logout

Invalida token JWT atual.

**Endpoint**: `POST /api/auth/logout.php`

**Autenticação**: Requerida

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

---

### 4. Recuperar Senha

Envia email com link para redefinir senha.

**Endpoint**: `POST /api/auth/forgot-password.php`

**Autenticação**: Não requerida

**Parâmetros**:
```json
{
  "email": "joao@email.com"
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "message": "Email enviado com instruções"
}
```

---

## 👤 Usuários

### 5. Obter Perfil

Retorna dados do perfil do usuário.

**Endpoint**: `GET /api/users/profile.php`

**Autenticação**: Requerida

**Parâmetros Query**:
- `user_id` (opcional): ID do usuário (admin pode ver outros)

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "João Silva",
    "email": "joao@email.com",
    "cpf": "123.456.789-00",
    "phone": "(11) 99999-9999",
    "type": "user",
    "status": "active",
    "wallet": {
      "id": 456,
      "balance": 150.00,
      "blocked_balance": 0.00
    },
    "referral_code": "ABC123",
    "referred_by": null,
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-11-17T09:30:00Z"
  }
}
```

---

### 6. Atualizar Perfil

Atualiza dados do usuário.

**Endpoint**: `PUT /api/users/update.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "name": "João da Silva",
  "phone": "(11) 98888-8888",
  "address": {
    "street": "Rua Example",
    "number": "123",
    "city": "São Paulo",
    "state": "SP",
    "zip": "01234-567"
  }
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "message": "Perfil atualizado com sucesso"
}
```

---

### 7. Listar Usuários

Lista todos os usuários (admin apenas).

**Endpoint**: `GET /api/users/list.php`

**Autenticação**: Requerida (Admin)

**Parâmetros Query**:
- `page`: Número da página (padrão: 1)
- `limit`: Itens por página (padrão: 20)
- `status`: Filtrar por status (active, blocked, pending)
- `type`: Filtrar por tipo (user, merchant, admin)
- `search`: Buscar por nome, email ou CPF

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 123,
        "name": "João Silva",
        "email": "joao@email.com",
        "cpf": "123.456.789-00",
        "type": "user",
        "status": "active",
        "wallet_balance": 150.00,
        "created_at": "2025-01-15T10:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 10,
      "total_items": 200,
      "items_per_page": 20
    }
  }
}
```

---

## 🏪 Lojas

### 8. Criar Loja

Cria nova loja (lojista).

**Endpoint**: `POST /api/stores/create.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "name": "Loja Example",
  "trade_name": "Example Store",
  "cnpj": "12.345.678/0001-90",
  "email": "loja@example.com",
  "phone": "(11) 99999-9999",
  "category": "alimentacao",
  "address": {
    "street": "Rua Example",
    "number": "123",
    "complement": "Sala 1",
    "neighborhood": "Centro",
    "city": "São Paulo",
    "state": "SP",
    "zip": "01234-567"
  },
  "commission_rate": 5.0  // Percentual de comissão
}
```

**Resposta Sucesso** (201):
```json
{
  "success": true,
  "data": {
    "store_id": 789,
    "status": "pending_approval"
  },
  "message": "Loja criada. Aguardando aprovação."
}
```

---

### 9. Aprovar Loja

Aprova loja pendente (admin apenas).

**Endpoint**: `POST /api/stores/approve.php`

**Autenticação**: Requerida (Admin)

**Parâmetros**:
```json
{
  "store_id": 789,
  "approved": true,
  "notes": "Documentação aprovada"
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "message": "Loja aprovada com sucesso"
}
```

---

### 10. Listar Lojas

Lista lojas cadastradas.

**Endpoint**: `GET /api/stores/list.php`

**Autenticação**: Requerida

**Parâmetros Query**:
- `page`: Número da página
- `limit`: Itens por página
- `status`: Filtrar por status (pending, approved, rejected, blocked)
- `category`: Filtrar por categoria
- `search`: Buscar por nome ou CNPJ

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "stores": [
      {
        "id": 789,
        "name": "Loja Example",
        "trade_name": "Example Store",
        "cnpj": "12.345.678/0001-90",
        "category": "alimentacao",
        "status": "approved",
        "commission_rate": 5.0,
        "owner": {
          "id": 123,
          "name": "João Silva"
        },
        "created_at": "2025-01-20T14:00:00Z"
      }
    ],
    "pagination": { /* ... */ }
  }
}
```

---

### 11. Obter Detalhes da Loja

Retorna detalhes completos de uma loja.

**Endpoint**: `GET /api/stores/details.php?store_id=789`

**Autenticação**: Requerida

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "id": 789,
    "name": "Loja Example",
    "trade_name": "Example Store",
    "cnpj": "12.345.678/0001-90",
    "email": "loja@example.com",
    "phone": "(11) 99999-9999",
    "category": "alimentacao",
    "status": "approved",
    "commission_rate": 5.0,
    "address": { /* endereço completo */ },
    "owner": {
      "id": 123,
      "name": "João Silva",
      "email": "joao@email.com"
    },
    "wallet": {
      "id": 999,
      "balance": 5000.00
    },
    "stats": {
      "total_sales": 150,
      "total_revenue": 15000.00,
      "total_commission": 750.00
    },
    "created_at": "2025-01-20T14:00:00Z",
    "updated_at": "2025-11-17T09:00:00Z"
  }
}
```

---

## 💰 Transações

### 12. Criar Transação

Cria nova transação financeira.

**Endpoint**: `POST /api/transactions/create.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "type": "deposit",  // deposit, withdrawal, transfer, commission, refund
  "amount": 100.00,
  "description": "Depósito via PIX",
  "metadata": {
    "payment_method": "pix",
    "payment_id": "mp_12345"
  }
}
```

**Resposta Sucesso** (201):
```json
{
  "success": true,
  "data": {
    "transaction_id": "TXN_1234567890",
    "status": "pending",
    "amount": 100.00,
    "created_at": "2025-11-17T10:00:00Z"
  },
  "message": "Transação criada com sucesso"
}
```

---

### 13. Listar Transações

Lista transações do usuário ou loja.

**Endpoint**: `GET /api/transactions/list.php`

**Autenticação**: Requerida

**Parâmetros Query**:
- `page`: Número da página
- `limit`: Itens por página
- `type`: Filtrar por tipo
- `status`: Filtrar por status (pending, completed, failed, cancelled)
- `start_date`: Data inicial (YYYY-MM-DD)
- `end_date`: Data final (YYYY-MM-DD)

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": "TXN_1234567890",
        "type": "deposit",
        "amount": 100.00,
        "status": "completed",
        "description": "Depósito via PIX",
        "from": null,
        "to": {
          "user_id": 123,
          "name": "João Silva"
        },
        "metadata": { /* ... */ },
        "created_at": "2025-11-17T10:00:00Z",
        "completed_at": "2025-11-17T10:01:00Z"
      }
    ],
    "pagination": { /* ... */ },
    "summary": {
      "total_in": 1000.00,
      "total_out": 500.00,
      "net": 500.00
    }
  }
}
```

---

### 14. Obter Detalhes da Transação

Retorna detalhes completos de uma transação.

**Endpoint**: `GET /api/transactions/details.php?transaction_id=TXN_1234567890`

**Autenticação**: Requerida

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "id": "TXN_1234567890",
    "type": "deposit",
    "amount": 100.00,
    "status": "completed",
    "description": "Depósito via PIX",
    "from": null,
    "to": {
      "user_id": 123,
      "name": "João Silva",
      "wallet_id": 456
    },
    "payment_method": "pix",
    "payment_provider": "mercadopago",
    "external_id": "mp_12345",
    "metadata": { /* metadados completos */ },
    "audit_trail": [
      {
        "status": "pending",
        "timestamp": "2025-11-17T10:00:00Z"
      },
      {
        "status": "completed",
        "timestamp": "2025-11-17T10:01:00Z"
      }
    ],
    "created_at": "2025-11-17T10:00:00Z",
    "updated_at": "2025-11-17T10:01:00Z"
  }
}
```

---

### 15. Histórico de Carteira

Retorna histórico completo da carteira.

**Endpoint**: `GET /api/transactions/wallet-history.php`

**Autenticação**: Requerida

**Parâmetros Query**:
- `wallet_id`: ID da carteira (opcional, padrão é a principal)
- `start_date`, `end_date`: Período
- `page`, `limit`: Paginação

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "wallet": {
      "id": 456,
      "balance": 150.00,
      "blocked_balance": 0.00
    },
    "history": [
      {
        "date": "2025-11-17",
        "transactions": [
          {
            "id": "TXN_1234567890",
            "type": "deposit",
            "amount": 100.00,
            "description": "Depósito via PIX",
            "timestamp": "2025-11-17T10:00:00Z"
          }
        ],
        "daily_total": 100.00
      }
    ],
    "pagination": { /* ... */ }
  }
}
```

---

## 💳 Pagamentos

### 16. Criar Pagamento

Inicia processo de pagamento.

**Endpoint**: `POST /api/payments/create.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "amount": 100.00,
  "method": "pix",  // pix, credit_card
  "provider": "mercadopago",  // mercadopago, stripe, abacatepay, openpix
  "description": "Recarga de saldo",
  "payment_data": {
    // Para cartão de crédito
    "card_token": "card_token_12345",
    "installments": 1
  }
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "payment_id": "PAY_1234567890",
    "status": "pending",
    "amount": 100.00,
    "method": "pix",
    "pix_data": {
      "qr_code": "00020126580014br.gov.bcb.pix...",
      "qr_code_base64": "data:image/png;base64,...",
      "expires_at": "2025-11-17T11:00:00Z"
    }
  },
  "message": "Pagamento criado. Aguardando confirmação."
}
```

---

### 17. Status do Pagamento

Consulta status de um pagamento.

**Endpoint**: `GET /api/payments/status.php?payment_id=PAY_1234567890`

**Autenticação**: Requerida

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "payment_id": "PAY_1234567890",
    "status": "approved",  // pending, approved, rejected, cancelled
    "amount": 100.00,
    "method": "pix",
    "approved_at": "2025-11-17T10:05:00Z",
    "transaction_id": "TXN_1234567890"
  }
}
```

---

### 18. Processar Webhook

Recebe notificações de provedores de pagamento.

**Endpoint**: `POST /webhooks/mercadopago.php`

**Autenticação**: Assinatura do provedor

**Nota**: Este endpoint é chamado automaticamente pelos provedores de pagamento.

---

## 📅 Assinaturas

### 19. Criar Assinatura

Cria nova assinatura de plano.

**Endpoint**: `POST /api/subscriptions/create.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "plan": "monthly",  // monthly, annual
  "payment_method": "credit_card",
  "payment_data": {
    "card_token": "card_token_12345"
  }
}
```

**Resposta Sucesso** (201):
```json
{
  "success": true,
  "data": {
    "subscription_id": 12345,
    "plan": "monthly",
    "status": "active",
    "amount": 29.90,
    "next_billing_date": "2025-12-17",
    "created_at": "2025-11-17T10:00:00Z"
  },
  "message": "Assinatura criada com sucesso"
}
```

---

### 20. Upgrade de Assinatura

Faz upgrade do plano com cálculo proporcional.

**Endpoint**: `POST /api/subscriptions/upgrade.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "subscription_id": 12345,
  "new_plan": "annual"
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "subscription_id": 12345,
    "old_plan": "monthly",
    "new_plan": "annual",
    "credit_applied": 15.00,
    "amount_to_pay": 274.80,  // 289.90 - 15.00
    "next_billing_date": "2026-11-17"
  },
  "message": "Upgrade realizado com sucesso"
}
```

---

### 21. Cancelar Assinatura

Cancela assinatura ativa.

**Endpoint**: `POST /api/subscriptions/cancel.php`

**Autenticação**: Requerida

**Parâmetros**:
```json
{
  "subscription_id": 12345,
  "reason": "Não estou usando mais o serviço"
}
```

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "subscription_id": 12345,
    "status": "cancelled",
    "active_until": "2025-12-17",
    "cancelled_at": "2025-11-17T10:00:00Z"
  },
  "message": "Assinatura cancelada. Acesso até 2025-12-17."
}
```

---

## 💵 Comissões

### 22. Listar Comissões

Lista comissões recebidas.

**Endpoint**: `GET /api/commissions/list.php`

**Autenticação**: Requerida

**Parâmetros Query**:
- `page`, `limit`: Paginação
- `start_date`, `end_date`: Período
- `status`: Filtrar por status (pending, paid)

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "commissions": [
      {
        "id": 9876,
        "type": "sale",  // sale, referral
        "amount": 5.00,
        "rate": 5.0,
        "status": "paid",
        "transaction": {
          "id": "TXN_1234567890",
          "amount": 100.00
        },
        "from": {
          "store_id": 789,
          "store_name": "Loja Example"
        },
        "created_at": "2025-11-17T10:00:00Z",
        "paid_at": "2025-11-17T10:01:00Z"
      }
    ],
    "pagination": { /* ... */ },
    "summary": {
      "total_earned": 500.00,
      "pending": 50.00,
      "paid": 450.00
    }
  }
}
```

---

## 👔 Funcionários

### 23. Criar Funcionário

Adiciona funcionário a uma loja (lojista apenas).

**Endpoint**: `POST /api/employees/create.php`

**Autenticação**: Requerida (Lojista)

**Parâmetros**:
```json
{
  "store_id": 789,
  "name": "Maria Santos",
  "email": "maria@example.com",
  "cpf": "987.654.321-00",
  "phone": "(11) 98888-8888",
  "role": "vendedor",  // vendedor, gerente
  "permissions": {
    "register_sales": true,
    "view_reports": false
  }
}
```

**Resposta Sucesso** (201):
```json
{
  "success": true,
  "data": {
    "employee_id": 5555,
    "temporary_password": "temp123456"
  },
  "message": "Funcionário criado. Senha temporária enviada por email."
}
```

---

### 24. Listar Funcionários

Lista funcionários de uma loja.

**Endpoint**: `GET /api/employees/list.php?store_id=789`

**Autenticação**: Requerida (Lojista)

**Resposta Sucesso** (200):
```json
{
  "success": true,
  "data": {
    "employees": [
      {
        "id": 5555,
        "name": "Maria Santos",
        "email": "maria@example.com",
        "role": "vendedor",
        "status": "active",
        "created_at": "2025-11-10T09:00:00Z"
      }
    ]
  }
}
```

---

## 📊 Códigos de Status HTTP

### Sucesso
- `200 OK`: Requisição bem-sucedida
- `201 Created`: Recurso criado com sucesso
- `204 No Content`: Sucesso sem conteúdo de retorno

### Erro Cliente
- `400 Bad Request`: Dados inválidos
- `401 Unauthorized`: Não autenticado
- `403 Forbidden`: Sem permissão
- `404 Not Found`: Recurso não encontrado
- `409 Conflict`: Conflito (ex: CPF duplicado)
- `422 Unprocessable Entity`: Validação falhou

### Erro Servidor
- `500 Internal Server Error`: Erro interno
- `503 Service Unavailable`: Serviço indisponível

---

## 🔧 Códigos de Erro Customizados

```
AUTH_001: Invalid credentials
AUTH_002: Token expired
AUTH_003: Token invalid
AUTH_004: User blocked

USER_001: User not found
USER_002: CPF already exists
USER_003: Email already exists

STORE_001: Store not found
STORE_002: Store not approved
STORE_003: CNPJ already exists

TXN_001: Insufficient balance
TXN_002: Transaction not found
TXN_003: Transaction already processed

PAY_001: Payment failed
PAY_002: Payment cancelled
PAY_003: Invalid payment method

SUB_001: Subscription not found
SUB_002: Already subscribed
SUB_003: Payment failed
```

---

## 📚 Próximos Passos

- **[[04-banco-de-dados]]** - Entenda as tabelas e relacionamentos
- **[[05-integracoes]]** - Saiba mais sobre integrações de pagamento
- **[[08-guia-desenvolvimento]]** - Comece a integrar com as APIs

---

## 📝 Notas Importantes

1. **Rate Limiting**: APIs têm limite de 100 requisições por minuto por IP
2. **Tokens JWT**: Expiram em 24 horas
3. **Webhooks**: Devem responder com status 200 em até 5 segundos
4. **PIX QR Codes**: Expiram em 1 hora
5. **Paginação**: Máximo de 100 itens por página

---

**Última atualização**: 2025-11-17
