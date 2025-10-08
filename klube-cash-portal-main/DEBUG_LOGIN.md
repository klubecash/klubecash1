# Debug: Failed to Fetch - Guia de Solução

## Problema: "Failed to fetch" ao tentar fazer login

Este erro significa que o frontend React não consegue se conectar ao backend PHP.

## ✅ Solução Passo a Passo

### Passo 1: Verificar se o Backend está Rodando

Abra um terminal e execute:

```bash
cd C:\Users\Kaua\Documents\Projetos\klubecash
php -S localhost:8000
```

**IMPORTANTE:** Deixe este terminal aberto! O servidor PHP precisa ficar rodando.

Você deve ver algo como:
```
PHP 7.x Development Server (http://localhost:8000) started
```

### Passo 2: Testar o Backend Diretamente

Abra o navegador e acesse:

```
http://localhost:8000/api/auth-check.php
```

**Resposta esperada:**
```json
{
  "status": false,
  "authenticated": false
}
```

Se você vir esta resposta, o backend está funcionando! ✅

Se der erro 404 ou não carregar, o problema é no backend.

### Passo 3: Corrigir o arquivo .env

O arquivo `.env` precisa apontar para a porta correta do backend.

**Edite o arquivo:** `klube-cash-portal-main\.env`

```env
# CORRETO - Com a porta 8000
VITE_API_BASE_URL=http://localhost:8000
VITE_API_ENDPOINT=/api
```

**ATENÇÃO:** Depois de editar o `.env`, você DEVE reiniciar o npm run dev!

### Passo 4: Reiniciar o Frontend

1. Pare o servidor Vite (Ctrl+C no terminal)
2. Inicie novamente:

```bash
cd C:\Users\Kaua\Documents\Projetos\klubecash\klube-cash-portal-main
npm run dev
```

### Passo 5: Limpar Cache do Navegador

1. Abra o DevTools (F12)
2. Clique com botão direito no botão de recarregar
3. Selecione "Limpar cache e recarregar forçadamente"

Ou simplesmente use: **Ctrl + Shift + R**

### Passo 6: Testar Login Novamente

1. Acesse `http://localhost:5173`
2. Tente fazer login com:
   - Email: `kaua@klubecash.com` (ou outro usuário)
   - Senha: a senha do usuário

---

## 🔍 Verificações Adicionais

### Verificar CORS no Backend

Os arquivos da API precisam ter os headers CORS corretos. Verifique se os arquivos em `api/` têm:

```php
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

### Verificar Logs no DevTools

1. Abra o DevTools (F12)
2. Vá para a aba "Console"
3. Tente fazer login
4. Veja se há erros em vermelho

**Erros comuns:**

- **CORS error**: Problema de CORS (veja solução abaixo)
- **Failed to fetch**: Backend não está rodando ou URL errada
- **404 Not Found**: Endpoint não existe
- **500 Internal Server Error**: Erro no código PHP

### Verificar Rede no DevTools

1. Abra o DevTools (F12)
2. Vá para a aba "Network" (Rede)
3. Tente fazer login
4. Veja as requisições que são feitas

Procure por:
- `auth-login.php` - deve aparecer na lista
- Status: deve ser 200 (sucesso) ou 401 (credenciais erradas)
- Se estiver vermelho = problema de conexão

---

## 🛠️ Soluções para Problemas Específicos

### Problema: Erro de CORS

**Sintoma:** Console mostra "CORS policy blocked"

**Solução:** Atualize os arquivos de API com os headers corretos.

Eu vou criar um script para corrigir isso automaticamente.

### Problema: Backend não inicia

**Sintoma:** Erro ao executar `php -S localhost:8000`

**Possíveis causas:**
1. Porta 8000 já está em uso
2. PHP não está instalado ou no PATH

**Solução 1 - Usar outra porta:**
```bash
php -S localhost:8080
```

Então atualize o `.env`:
```env
VITE_API_BASE_URL=http://localhost:8080
```

**Solução 2 - Verificar se PHP está instalado:**
```bash
php --version
```

Se der erro, você precisa instalar o PHP ou usar XAMPP/WAMP.

### Problema: Usando XAMPP/WAMP

Se você está usando XAMPP ou WAMP ao invés de `php -S`:

**Configuração no .env:**
```env
# Para XAMPP (porta padrão 80)
VITE_API_BASE_URL=http://localhost/klubecash
VITE_API_ENDPOINT=/api

# Para XAMPP (porta customizada, ex: 8080)
VITE_API_BASE_URL=http://localhost:8080/klubecash
VITE_API_ENDPOINT=/api
```

---

## 📝 Checklist Completo

Use esta checklist para garantir que tudo está correto:

- [ ] Backend PHP está rodando (`php -S localhost:8000`)
- [ ] Consigo acessar `http://localhost:8000/api/auth-check.php` no navegador
- [ ] Arquivo `.env` tem a URL correta (`http://localhost:8000`)
- [ ] Reiniciei o `npm run dev` depois de editar o `.env`
- [ ] Limpei o cache do navegador (Ctrl + Shift + R)
- [ ] Usuário existe no banco de dados e está ativo
- [ ] Verificado que não há erros no Console (F12)

---

## 🎯 Script de Teste Rápido

Salve este arquivo como `test-api.html` e abra no navegador:

```html
<!DOCTYPE html>
<html>
<head>
    <title>Teste API Klube Cash</title>
</head>
<body>
    <h1>Teste de Conexão com API</h1>
    <button onclick="testarAPI()">Testar Conexão</button>
    <pre id="resultado"></pre>

    <script>
        async function testarAPI() {
            const resultado = document.getElementById('resultado');
            resultado.textContent = 'Testando...';

            try {
                const response = await fetch('http://localhost:8000/api/auth-check.php', {
                    credentials: 'include'
                });
                const data = await response.json();
                resultado.textContent = JSON.stringify(data, null, 2);

                if (data.status !== undefined) {
                    alert('✅ API funcionando! Backend está OK!');
                }
            } catch (error) {
                resultado.textContent = 'ERRO: ' + error.message;
                alert('❌ Erro: Backend não está acessível!\n\n' + error.message);
            }
        }
    </script>
</body>
</html>
```

Se este teste funcionar, o problema está no frontend React.
Se não funcionar, o problema está no backend PHP.

---

## 📞 Precisa de Ajuda?

Se nenhuma solução funcionou, me envie estas informações:

1. **Erro no Console** (F12 > Console)
2. **Erro na aba Network** (F12 > Network)
3. **Saída do terminal** onde o PHP está rodando
4. **Saída do terminal** onde o npm está rodando
5. **Conteúdo do arquivo .env**

Com essas informações, posso identificar o problema específico!
