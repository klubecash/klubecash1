# 🌐 Usando o Backend Oficial klubecash.com

## ✅ Configuração Concluída

O portal agora está configurado para usar o backend oficial `https://klubecash.com`!

### Arquivos Atualizados:

1. **`.env`** - Apontando para klubecash.com
2. **`test-api.html`** - Configurado para testar klubecash.com
3. **Arquivos API** - CORS atualizado para aceitar requisições externas

---

## 🚀 Como Usar

### Passo 1: Fazer Upload dos Arquivos API

Os arquivos em `api/` foram atualizados com CORS correto. Você precisa fazer upload deles para o servidor:

**Arquivos que DEVEM ser enviados para https://klubecash.com:**

```
api/auth-login.php    ✅ Atualizado
api/auth-logout.php   ✅ Atualizado
api/auth-check.php    ✅ Atualizado
api/dashboard.php     ✅ Atualizado
```

Use FTP, cPanel ou seu método preferido para fazer upload.

---

### Passo 2: Testar o Backend

1. Abra o arquivo: `C:\Users\Kaua\Documents\Projetos\klubecash\test-api.html`
2. Arraste para o navegador
3. Clique em "Testar Backend"

**Resultado esperado:**
```json
{
  "status": false,
  "authenticated": false
}
```

Se aparecer este JSON = ✅ Backend funcionando!

---

### Passo 3: Reiniciar o Frontend

O arquivo `.env` foi alterado, então você DEVE reiniciar:

```bash
# Pare o servidor (Ctrl+C)
cd C:\Users\Kaua\Documents\Projetos\klubecash\klube-cash-portal-main
npm run dev
```

---

### Passo 4: Testar Login

1. Acesse `http://localhost:5173`
2. Pressione **Ctrl + Shift + R** (limpar cache)
3. Tente fazer login com:
   - Email: `kaua@syncholding.com.br`
   - Senha: (sua senha)

---

## 🧪 Testar Login Diretamente

No arquivo `test-api.html`:

1. Digite seu email
2. Digite sua senha
3. Clique em "Fazer Login"

**Se funcionar aqui mas não no portal:**
- O problema está no frontend React
- Verifique o Console (F12)

**Se não funcionar nem aqui:**
- Verifique se os arquivos PHP foram enviados
- Verifique se a senha está correta
- Verifique se o usuário existe e está ativo

---

## 🔑 Credenciais de Teste

### Criar Usuário de Teste no Servidor

Execute este SQL no banco de dados do servidor `klubecash.com`:

```sql
-- 1. Criar usuário
INSERT INTO usuarios (nome, email, senha_hash, tipo, status, data_criacao)
VALUES (
  'Portal Teste',
  'portal@teste.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'loja',
  'ativo',
  NOW()
);

-- 2. Pegar ID
SET @usuario_id = LAST_INSERT_ID();

-- 3. Criar loja
INSERT INTO lojas (nome_fantasia, email, usuario_id, status, categoria, porcentagem_cashback, data_criacao)
VALUES (
  'Loja Portal Teste',
  'portal@teste.com',
  @usuario_id,
  'aprovado',
  'Varejo',
  5.00,
  NOW()
);
```

**Credenciais:**
- Email: `portal@teste.com`
- Senha: `password`

---

## 📋 Checklist de Verificação

Antes de testar o login, verifique:

- [ ] Arquivos `api/*.php` foram enviados para o servidor
- [ ] Arquivo `.env` aponta para `https://klubecash.com`
- [ ] Reiniciei o `npm run dev`
- [ ] Testei com `test-api.html` e funcionou
- [ ] Usuário existe no banco de dados do servidor
- [ ] Usuário está com status 'ativo'
- [ ] Se for loja, tem uma loja vinculada com status 'aprovado'

---

## 🔍 Debug com DevTools

### Console (F12 > Console)

**Erros comuns:**

❌ `CORS policy blocked` = Arquivos API não foram atualizados no servidor

❌ `Failed to fetch` = URL errada ou servidor offline

❌ `401 Unauthorized` = Usuário/senha incorretos

✅ Sem erros = Tudo OK!

### Network (F12 > Network)

Procure por `auth-login.php`:

- **Vermelho** = Erro de conexão ou CORS
- **Status 200** = Sucesso!
- **Status 401** = Credenciais erradas
- **Status 500** = Erro no PHP (verifique logs do servidor)

---

## 🛠️ Troubleshooting

### Problema: "Failed to fetch" ainda aparece

**Causa:** Arquivos API não foram enviados para o servidor

**Solução:**
1. Faça upload dos arquivos em `api/` para `klubecash.com/api/`
2. Verifique se têm permissão de execução
3. Teste acessando diretamente: `https://klubecash.com/api/auth-check.php`

### Problema: Erro de CORS

**Causa:** Versão antiga dos arquivos API no servidor

**Solução:**
1. Confirme que enviou as versões atualizadas
2. Os arquivos DEVEM ter o código de CORS que está nos arquivos locais
3. Limpe o cache do navegador (Ctrl + Shift + R)

### Problema: Login retorna erro 401

**Causa:** Usuário não existe ou senha incorreta

**Solução:**
1. Verifique no banco de dados do servidor se o usuário existe
2. Use o email exato (case-sensitive)
3. Se não sabe a senha, crie um usuário de teste novo

### Problema: Login funciona mas não redireciona

**Causa:** Sessão PHP não está sendo mantida

**Solução:**
1. Verifique se as sessões PHP estão habilitadas no servidor
2. Confirme que `session.cookie_secure` está configurado corretamente
3. Para HTTPS, pode ser necessário ajustar configurações de cookies

---

## 📊 Estrutura Atual

```
Frontend (React):
http://localhost:5173
↓
API (PHP):
https://klubecash.com/api/
↓
Banco de Dados:
MySQL em klubecash.com
```

---

## 🎯 Próximos Passos

Depois que o login funcionar:

1. ✅ Teste outras páginas do portal
2. ✅ Verifique se os dados do dashboard carregam
3. ✅ Teste criar transações
4. ✅ Configure deploy do frontend (se necessário)

---

## 📞 Precisa de Ajuda?

Se ainda não funcionar, me envie:

1. Screenshot do `test-api.html` após clicar em "Testar Backend"
2. Screenshot do Console (F12 > Console) ao tentar fazer login
3. Screenshot do Network (F12 > Network) mostrando a requisição `auth-login.php`
4. Confirmação de que os arquivos API foram enviados para o servidor

Com essas informações, posso identificar o problema exato! 🎯
