# Setup Completo - Klube Cash Portal

## ✅ O que foi implementado

### 1. Sistema de Autenticação
- ✅ Página de login ([src/pages/Login.tsx](src/pages/Login.tsx))
- ✅ Serviço de autenticação ([src/services/authService.ts](src/services/authService.ts))
- ✅ Endpoints de API no backend:
  - `api/auth-login.php` - Login
  - `api/auth-logout.php` - Logout
  - `api/auth-check.php` - Verificar sessão

### 2. Proteção de Rotas
- ✅ Componente `ProtectedRoute` em [App.tsx](src/App.tsx)
- ✅ Todas as rotas protegidas (exceto `/login`)
- ✅ Redirecionamento automático para login quando não autenticado

### 3. Conexão com Backend
- ✅ Cliente API completo ([src/lib/api.ts](src/lib/api.ts))
- ✅ Hook customizado `useApi` ([src/hooks/useApi.ts](src/hooks/useApi.ts))
- ✅ Serviço de Dashboard ([src/services/dashboardService.ts](src/services/dashboardService.ts))
- ✅ Endpoint de dashboard ([api/dashboard.php](../api/dashboard.php))

### 4. Dashboard com Dados Reais
- ✅ [Dashboard.tsx](src/pages/Dashboard.tsx) conectado ao backend
- ✅ Estados de loading com skeletons
- ✅ Tratamento de erros
- ✅ Fallback para dados mock quando API não disponível

### 5. Interface de Usuário
- ✅ Sidebar com informações do usuário logado
- ✅ Botão de logout funcional
- ✅ Avatar e nome do usuário dinâmicos
- ✅ Diálogo de confirmação de logout

## 📋 Como usar

### Passo 1: Configurar Backend

1. Certifique-se de que o PHP está rodando:
   ```bash
   cd C:\Users\Kaua\Documents\Projetos\klubecash
   php -S localhost:8000
   ```

2. Verifique se há usuários no banco de dados:
   - Execute o arquivo [check-users.sql](check-users.sql) no seu cliente MySQL
   - Ou acesse phpMyAdmin e rode as queries

### Passo 2: Configurar Frontend

1. Configure o arquivo `.env`:
   ```env
   VITE_API_BASE_URL=http://localhost:8000
   VITE_API_ENDPOINT=/api
   ```

2. Instale dependências e inicie o servidor:
   ```bash
   cd klube-cash-portal-main
   npm install
   npm run dev
   ```

### Passo 3: Fazer Login

1. Acesse `http://localhost:5173`
2. Você será redirecionado para `/login`
3. Use as credenciais de um usuário do banco de dados
4. Veja os dados reais no Dashboard!

## 🔑 Criar Usuário de Teste

Se não houver usuários, execute este SQL:

```sql
-- Criar usuário
INSERT INTO usuarios (nome, email, senha_hash, tipo, status, data_criacao)
VALUES (
  'Loja Teste',
  'loja@teste.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'loja',
  'ativo',
  NOW()
);

SET @usuario_id = LAST_INSERT_ID();

-- Criar loja
INSERT INTO lojas (nome_fantasia, email, usuario_id, status, categoria, porcentagem_cashback, data_criacao)
VALUES (
  'Loja Teste',
  'loja@teste.com',
  @usuario_id,
  'aprovado',
  'Varejo',
  5.00,
  NOW()
);
```

**Credenciais:**
- Email: `loja@teste.com`
- Senha: `password`

## 📁 Arquivos Importantes

### Frontend
```
klube-cash-portal-main/
├── .env                           # Configuração de ambiente
├── src/
│   ├── App.tsx                    # Rotas e proteção
│   ├── pages/
│   │   ├── Login.tsx              # Página de login
│   │   └── Dashboard.tsx          # Dashboard com dados reais
│   ├── services/
│   │   ├── authService.ts         # Autenticação
│   │   └── dashboardService.ts    # Dados do dashboard
│   ├── hooks/
│   │   └── useApi.ts              # Hook de requisições
│   ├── lib/
│   │   └── api.ts                 # Cliente HTTP
│   └── components/
│       ├── AppSidebar.tsx         # Sidebar com logout
│       └── Layout.tsx             # Layout principal
```

### Backend
```
api/
├── auth-login.php                 # Login
├── auth-logout.php                # Logout
├── auth-check.php                 # Verificar sessão
└── dashboard.php                  # Dados do dashboard
```

## 🔍 Documentação Adicional

- [LOGIN_GUIDE.md](LOGIN_GUIDE.md) - Guia completo de login
- [BACKEND_SETUP.md](BACKEND_SETUP.md) - Configuração do backend
- [QUICK_START.md](QUICK_START.md) - Início rápido
- [check-users.sql](check-users.sql) - Script para verificar usuários

## 🧪 Testar Conexão

### 1. Testar API diretamente

Abra o navegador ou Postman e teste:

**Verificar sessão:**
```
GET http://localhost:8000/api/auth-check.php
```

**Login:**
```
POST http://localhost:8000/api/auth-login.php
Content-Type: application/json

{
  "email": "loja@teste.com",
  "password": "password"
}
```

**Dashboard (após login):**
```
GET http://localhost:8000/api/dashboard.php
```

### 2. Verificar logs

No terminal onde está rodando `npm run dev`:
- Procure por erros de requisição
- Verifique se as URLs estão corretas

No navegador (F12 > Network):
- Veja as requisições para `/api/`
- Verifique os status codes (200 = OK, 401 = Não autenticado, 500 = Erro)

### 3. Testar fluxo completo

1. ✅ Acesse o portal
2. ✅ Seja redirecionado para login
3. ✅ Faça login com credenciais válidas
4. ✅ Veja o dashboard com dados reais
5. ✅ Navegue entre páginas
6. ✅ Faça logout
7. ✅ Seja redirecionado para login novamente

## ⚠️ Problemas Comuns

### Erro de CORS
- Verifique se o backend tem os headers CORS corretos
- Confirme que a URL do frontend está permitida (`http://localhost:5173`)

### Sessão não persiste
- Certifique-se de que `credentials: 'include'` está configurado
- Verifique se o PHP está com sessões habilitadas

### Dados não aparecem
- Verifique se há transações no banco de dados
- Confira se a loja do usuário tem um ID válido
- Veja os logs do PHP para erros

### Login falha
- Confirme que o usuário existe e está ativo
- Verifique se a senha está correta
- Para usuário loja, confirme que existe uma loja vinculada

## 🎯 Próximos Passos

Agora que o login está funcionando, você pode:

1. Conectar outras páginas ao backend (Transações, Nova Venda, etc.)
2. Implementar funcionalidades específicas de cada página
3. Adicionar mais endpoints no backend conforme necessário
4. Melhorar a experiência do usuário com mais feedback visual

## 📞 Suporte

Para mais informações, consulte a documentação completa ou verifique os arquivos de exemplo incluídos no projeto.
