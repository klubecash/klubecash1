# Configuração da Conexão com Backend

Este documento explica como conectar o frontend React ao backend PHP do Klube Cash.

## 1. Configuração do Ambiente

### Passo 1: Configurar o arquivo `.env`

O arquivo `.env` já foi criado na raiz do projeto `klube-cash-portal-main`. Edite-o conforme necessário:

```env
# Backend API Configuration
VITE_API_BASE_URL=http://localhost
VITE_API_ENDPOINT=/api
```

**Importante:**
- `VITE_API_BASE_URL`: URL base do seu servidor PHP (ex: `http://localhost`, `http://localhost:8080`, ou seu domínio)
- `VITE_API_ENDPOINT`: Caminho para a pasta API (geralmente `/api`)

### Passo 2: Verificar o Backend PHP

Certifique-se de que seu servidor PHP está rodando e os arquivos da API estão acessíveis:

- O arquivo `api/dashboard.php` foi criado para fornecer dados do dashboard
- Verifique se o arquivo `config/database.php` está configurado corretamente
- Certifique-se de que as sessões PHP estão funcionando (o sistema usa `$_SESSION`)

## 2. Endpoints Criados

### Dashboard API (`/api/dashboard.php`)

**GET /api/dashboard.php**
- Retorna todos os dados do dashboard (KPIs + transações recentes)
- Requer autenticação via sessão PHP

**GET /api/dashboard.php?endpoint=kpi**
- Retorna apenas os KPIs do dashboard

**Resposta esperada:**
```json
{
  "status": true,
  "data": {
    "kpi": {
      "totalVendas": 248,
      "valorTotal": 125480.50,
      "pendentes": 12,
      "comissoes": 8450.20
    },
    "recentTransactions": [
      {
        "id": 1,
        "date": "2025-01-05",
        "client": "João Silva",
        "code": "TRX001",
        "value": 1250.00,
        "status": "aprovado"
      }
    ]
  }
}
```

## 3. Estrutura de Arquivos Criados

```
klube-cash-portal-main/
├── .env                          # Configuração do ambiente
├── .env.example                  # Exemplo de configuração
├── src/
│   ├── lib/
│   │   └── api.ts               # Cliente API e configuração
│   ├── hooks/
│   │   └── useApi.ts            # Hook personalizado para requisições
│   ├── services/
│   │   └── dashboardService.ts  # Serviço específico do dashboard
│   └── pages/
│       └── Dashboard.tsx        # Dashboard conectado ao backend
```

## 4. Como Funciona

### Cliente API (`src/lib/api.ts`)

Classe `ApiClient` que gerencia todas as requisições HTTP:
- Suporte a GET, POST, PUT, DELETE
- Envia cookies de sessão automaticamente (`credentials: 'include'`)
- Tratamento de erros integrado

### Hook useApi (`src/hooks/useApi.ts`)

Hook React personalizado para facilitar requisições:
- Estados de loading, error e data automatizados
- Suporte a callbacks de sucesso e erro
- Métodos: `fetchData`, `postData`, `putData`, `deleteData`

### Dashboard Service (`src/services/dashboardService.ts`)

Serviço específico para o dashboard com métodos:
- `getKPIData()`: Busca apenas KPIs
- `getRecentTransactions()`: Busca transações recentes
- `getDashboardData()`: Busca todos os dados (recomendado)
- `getStoreDetails()`: Busca detalhes de uma loja

## 5. Testando a Conexão

### Desenvolvimento Local

1. Inicie o servidor PHP (se não estiver rodando):
   ```bash
   # Se estiver usando PHP built-in server
   cd C:\Users\Kaua\Documents\Projetos\klubecash
   php -S localhost:8000
   ```

2. Inicie o frontend React:
   ```bash
   cd klube-cash-portal-main
   npm run dev
   ```

3. Acesse `http://localhost:5173` (ou a porta configurada pelo Vite)

### Verificar CORS

Se encontrar erros de CORS, certifique-se de que o backend PHP está configurado corretamente:

```php
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

## 6. Fallback para Dados Mock

O Dashboard foi implementado com fallback automático:
- Se a API falhar, ele usa dados de demonstração
- Exibe um alerta informando que está usando dados mock
- Permite testar a interface mesmo sem backend funcionando

## 7. Próximos Passos

Para conectar outras páginas ao backend:

1. Crie novos serviços em `src/services/` (ex: `transactionsService.ts`)
2. Use o hook `useApi` nas páginas
3. Implemente estados de loading e erro
4. Adicione endpoints correspondentes no backend PHP

### Exemplo:

```typescript
// src/services/transactionsService.ts
import { apiClient } from '@/lib/api';

export const transactionsService = {
  async getAll(filters?: any) {
    return apiClient.get('/transactions.php', filters);
  },

  async getById(id: number) {
    return apiClient.get('/transactions.php', { id });
  }
};

// Na página
import { useApi } from '@/hooks/useApi';
import { transactionsService } from '@/services/transactionsService';

function TransactionsPage() {
  const { data, loading, error, fetchData } = useApi('/transactions.php', {
    immediate: true
  });

  // ...
}
```

## 8. Troubleshooting

### A API não está respondendo
- Verifique se o servidor PHP está rodando
- Confirme a URL base no `.env`
- Verifique o console do navegador para erros de rede

### Erro de autenticação
- Certifique-se de que está logado no sistema PHP
- Verifique se as sessões PHP estão configuradas corretamente
- Confirme que `credentials: 'include'` está habilitado nas requisições

### Dados não aparecem
- Abra o DevTools do navegador → Network
- Verifique as requisições para `/api/dashboard.php`
- Confira a resposta JSON retornada pelo backend
- Olhe o console para erros JavaScript
