# Guia Rápido - Klube Cash Portal

## Início Rápido

### 1. Configurar Backend

Edite o arquivo `.env`:
```env
VITE_API_BASE_URL=http://localhost
VITE_API_ENDPOINT=/api
```

### 2. Instalar Dependências

```bash
npm install
```

### 3. Iniciar o Projeto

```bash
npm run dev
```

## Estrutura da Conexão com Backend

### Arquivo de Configuração
- `.env` - Variáveis de ambiente para configurar a URL do backend

### Arquivos Principais
- `src/lib/api.ts` - Cliente HTTP para comunicação com backend
- `src/hooks/useApi.ts` - Hook React para facilitar requisições
- `src/services/dashboardService.ts` - Serviço específico do Dashboard
- `src/pages/Dashboard.tsx` - Dashboard conectado ao backend

### Endpoints Backend (PHP)
- `GET /api/dashboard.php` - Retorna KPIs e transações recentes
- `GET /api/transactions.php` - Gerencia transações
- `GET /api/store_details.php` - Detalhes de lojas

## Como Usar em Outras Páginas

```typescript
import { useApi } from '@/hooks/useApi';

function MinhaPage() {
  const { data, loading, error, fetchData } = useApi('/seu-endpoint.php');

  useEffect(() => {
    fetchData();
  }, []);

  if (loading) return <div>Carregando...</div>;
  if (error) return <div>Erro: {error}</div>;

  return <div>{/* Seu conteúdo */}</div>;
}
```

## Verificar Logs

Para debug, abra o DevTools do navegador:
- **Console** - Erros JavaScript
- **Network** - Requisições HTTP
- Procure por requisições para `/api/`

## Suporte

Consulte `BACKEND_SETUP.md` para documentação completa.
