# 🔧 Solução Rápida - Failed to Fetch

## O Problema
Você está vendo "Failed to fetch" ao tentar fazer login. Isso significa que o frontend não consegue se conectar ao backend.

## ✅ Solução em 4 Passos

### 1️⃣ Iniciar o Backend PHP

Abra um terminal e execute:

```bash
cd C:\Users\Kaua\Documents\Projetos\klubecash
php -S localhost:8000
```

**Deixe este terminal ABERTO!** O servidor precisa ficar rodando.

---

### 2️⃣ Testar se o Backend está Funcionando

Abra o arquivo que eu criei:

```
C:\Users\Kaua\Documents\Projetos\klubecash\test-api.html
```

Arraste este arquivo para o navegador e clique em "Testar Backend".

**Se aparecer "✅ SUCESSO!"** → Backend OK, vá para o passo 3.

**Se aparecer "❌ ERRO!"** → Backend não está rodando. Volte ao passo 1.

---

### 3️⃣ Reiniciar o Frontend

O arquivo `.env` foi atualizado, então você precisa reiniciar:

1. No terminal onde está rodando `npm run dev`, pressione **Ctrl + C**
2. Execute novamente:

```bash
cd C:\Users\Kaua\Documents\Projetos\klubecash\klube-cash-portal-main
npm run dev
```

---

### 4️⃣ Limpar Cache e Testar

1. Acesse `http://localhost:5173`
2. Pressione **Ctrl + Shift + R** (limpa cache e recarrega)
3. Tente fazer login com:
   - Email: `kaua@klubecash.com`
   - Senha: (a senha que você usou para este usuário)

---

## 🧪 Teste de Login no HTML

Se o passo 2 funcionou, você pode testar o login direto no arquivo HTML:

1. Abra `test-api.html` no navegador
2. Digite email e senha
3. Clique em "Fazer Login"
4. Veja se funciona

Se funcionar no HTML mas não no portal React:
- O problema está no frontend React
- Verifique o Console do navegador (F12)

Se não funcionar nem no HTML:
- O problema está no backend PHP
- Verifique se o usuário existe no banco
- Verifique se a senha está correta

---

## 📝 Credenciais Disponíveis

Baseado no seu dump SQL, estes usuários podem fazer login:

### Admin:
- Email: `kaua@klubecash.com` (ID: 11)
- Email: `fredericofagundes0@gmail.com` (ID: 61)

### Lojas:
- Email: `kaua@syncholding.com.br` (ID: 159)
- Email: `acessoriafredericofagundes@gmail.com` (ID: 63)
- Email: `kauamathes123487654@gmail.com` (ID: 55)

**ATENÇÃO:** Você precisa saber a senha desses usuários!

---

## 🔑 Criar Novo Usuário de Teste

Se você não sabe a senha de nenhum usuário, execute este SQL:

```sql
-- 1. Criar usuário
INSERT INTO usuarios (nome, email, senha_hash, tipo, status, data_criacao)
VALUES (
  'Teste Portal',
  'teste@portal.com',
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
  'Loja Teste Portal',
  'teste@portal.com',
  @usuario_id,
  'aprovado',
  'Varejo',
  5.00,
  NOW()
);
```

**Credenciais criadas:**
- Email: `teste@portal.com`
- Senha: `password`

---

## 🐛 Ainda não Funciona?

Se ainda estiver com problema, verifique:

### No Terminal do PHP
Deve mostrar as requisições:
```
[200]: GET /api/auth-check.php
[200]: POST /api/auth-login.php
```

Se não aparecer nada quando você tenta fazer login = frontend não está chegando no backend.

### No DevTools do Navegador (F12)

**Aba Console:**
- Deve estar limpa, sem erros vermelhos
- Se tiver erro de CORS = problema de configuração
- Se tiver "Failed to fetch" = backend não está acessível

**Aba Network:**
- Procure por `auth-login.php`
- Se não aparecer = frontend não está fazendo a requisição
- Se aparecer em vermelho = erro de conexão
- Se aparecer e retornar 401 = usuário/senha errado
- Se aparecer e retornar 200 = sucesso!

---

## 📞 Informações para Debug

Se nada funcionar, me envie:

1. **Screenshot do teste HTML** (test-api.html)
2. **Erro no Console** (F12 > Console)
3. **Screenshot do Network** (F12 > Network > tentativa de login)
4. **Terminal do PHP** (o que aparece lá)
5. **URL que você está acessando** no navegador

Com isso consigo identificar o problema exato!
