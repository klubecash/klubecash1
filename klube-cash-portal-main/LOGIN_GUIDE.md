# Guia de Login - Klube Cash Portal

## Como fazer login com um usuário real

### Passo 1: Verificar usuários no banco de dados

Você precisa ter acesso ao banco de dados MySQL/MariaDB. Execute a seguinte query para ver os usuários disponíveis:

```sql
-- Ver usuários do tipo loja
SELECT id, nome, email, tipo, status, ultimo_login
FROM usuarios
WHERE tipo = 'loja'
AND status = 'ativo'
LIMIT 10;

-- Ver usuários do tipo funcionário
SELECT id, nome, email, tipo, status, loja_vinculada_id, ultimo_login
FROM usuarios
WHERE tipo = 'funcionario'
AND status = 'ativo'
LIMIT 10;

-- Ver todas as lojas aprovadas
SELECT l.id, l.nome_fantasia, l.email, l.usuario_id, l.status
FROM lojas l
WHERE l.status = 'aprovado'
LIMIT 10;
```

### Passo 2: Criar um usuário de teste (se necessário)

Se não houver usuários, você pode criar um usuário de teste:

```sql
-- Criar usuário lojista
INSERT INTO usuarios (nome, email, senha_hash, tipo, status, data_criacao)
VALUES (
  'Loja Teste',
  'loja@teste.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
  'loja',
  'ativo',
  NOW()
);

-- Pegar o ID do usuário criado
SET @usuario_id = LAST_INSERT_ID();

-- Criar loja vinculada ao usuário
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

**Credenciais de teste criadas:**
- Email: `loja@teste.com`
- Senha: `password`

### Passo 3: Iniciar o ambiente

#### Backend PHP:

```bash
# Navegue até a pasta do projeto
cd C:\Users\Kaua\Documents\Projetos\klubecash

# Inicie o servidor PHP (escolha uma das opções)
# Opção 1: PHP built-in server
php -S localhost:8000

# Opção 2: XAMPP/WAMP
# Certifique-se de que o Apache está rodando
# URL será: http://localhost/klubecash (ou conforme sua configuração)
```

#### Frontend React:

```bash
# Navegue até a pasta do portal
cd C:\Users\Kaua\Documents\Projetos\klubecash\klube-cash-portal-main

# Instale dependências (se ainda não instalou)
npm install

# Inicie o servidor de desenvolvimento
npm run dev
```

### Passo 4: Configurar o arquivo .env

Edite o arquivo `.env` no portal para apontar para seu backend:

```env
# Se estiver usando PHP built-in server na porta 8000
VITE_API_BASE_URL=http://localhost:8000
VITE_API_ENDPOINT=/api

# Se estiver usando XAMPP/WAMP
# VITE_API_BASE_URL=http://localhost/klubecash
# VITE_API_ENDPOINT=/api
```

**IMPORTANTE:** Depois de editar o `.env`, você precisa **reiniciar o npm run dev**!

### Passo 5: Fazer login

1. Acesse `http://localhost:5173` (ou a porta que o Vite mostrar)
2. Você será redirecionado para `/login`
3. Digite as credenciais do usuário
4. Clique em "Entrar"

### Passo 6: Verificar dados reais

Após o login bem-sucedido:

1. Você será redirecionado para o Dashboard
2. O sistema tentará carregar dados reais do backend
3. Se houver transações no banco, elas aparecerão na lista
4. Os KPIs mostrarão dados reais baseados nas transações da loja

## Troubleshooting

### Erro: "Email ou senha incorretos"

- Verifique se o usuário existe no banco de dados
- Confirme que o status é 'ativo'
- Se criou usuário manualmente, certifique-se de usar a senha correta

### Erro: "Nenhuma loja aprovada encontrada"

- Verifique se existe uma loja com `usuario_id` igual ao ID do usuário
- Confirme que o status da loja é 'aprovado'
- Execute a query de verificação de lojas

### Erro de CORS

Edite os arquivos em `api/` e certifique-se de que o header CORS está correto:

```php
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
```

### Dados não aparecem no Dashboard

1. Abra o DevTools do navegador (F12)
2. Vá para a aba "Network"
3. Recarregue a página
4. Procure por requisições para `/api/dashboard.php`
5. Clique na requisição e veja a resposta
6. Se houver erro 401, refaça o login
7. Se houver erro 500, verifique os logs do PHP

### Ver logs do PHP

No Windows com XAMPP:
```
C:\xampp\apache\logs\error.log
```

Com PHP built-in server, os erros aparecem no terminal onde você executou o comando.

## Estrutura de Sessão

O sistema usa sessões PHP. Após o login, estas variáveis são definidas:

```php
$_SESSION['user_id']        // ID do usuário
$_SESSION['user_name']      // Nome do usuário
$_SESSION['user_email']     // Email do usuário
$_SESSION['user_type']      // Tipo: 'loja', 'funcionario', 'cliente'
$_SESSION['store_id']       // ID da loja vinculada
$_SESSION['store_name']     // Nome da loja
```

Para verificar a sessão, você pode criar um arquivo temporário:

**check-session.php:**
```php
<?php
session_start();
header('Content-Type: application/json');
echo json_encode($_SESSION);
?>
```

Acesse: `http://localhost:8000/check-session.php`

## Próximos Passos

Depois de fazer login com sucesso:

1. Explore o Dashboard com dados reais
2. Teste outras páginas (Transações, Nova Venda, etc.)
3. Verifique se os dados são carregados corretamente
4. Teste o botão de logout no sidebar

## Consultas Úteis SQL

```sql
-- Ver transações de uma loja
SELECT * FROM transacoes WHERE loja_id = ? ORDER BY data_transacao DESC LIMIT 10;

-- Ver saldos de cashback
SELECT * FROM cashback_saldos WHERE loja_id = ? LIMIT 10;

-- Ver estatísticas de uma loja
SELECT
  COUNT(*) as total_transacoes,
  SUM(valor_total) as valor_total,
  SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes
FROM transacoes
WHERE loja_id = ?;
```
