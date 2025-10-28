# 🎯 Sistema de Seleção de Carteiras - KlubeCash

## 📋 Visão Geral

Sistema implementado para permitir que clientes com acesso ao programa SEST SENAT escolham entre duas carteiras digitais após fazer login:
1. **Klube Cash** - Carteira principal com cashback em lojas parceiras
2. **SEST SENAT** - Carteira exclusiva para benefícios do programa SEST SENAT

---

## 🔑 Como Funciona

### 1️⃣ Fluxo de Login Normal

Quando um usuário faz login em `https://klubecash.com/views/auth/login.php`:

```
┌─────────────────────────────────────────────────────────────┐
│  Cliente faz login                                          │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
         ┌────────────────┐
         │ senat = 'Sim'? │
         └────────┬───────┘
                  │
        ┌─────────┴─────────┐
        │                   │
       Sim                 Não
        │                   │
        ▼                   ▼
┌───────────────┐   ┌──────────────────┐
│ Redireciona   │   │ Redireciona para │
│ para          │   │ /cliente/        │
│ wallet-select │   │ dashboard        │
└───────────────┘   └──────────────────┘
```

### 2️⃣ Tela de Seleção de Carteira

**URL**: `https://klubecash.com/views/auth/wallet-select.php`

A página exibe dois cards elegantes:

#### Card 1: Klube Cash 💳
- **Destino**: `https://klubecash.com/cliente/dashboard`
- **Recursos**:
  - Cashback em lojas parceiras
  - Gestão de saldo e transações
  - Histórico completo
  - Resgate facilitado

#### Card 2: SEST SENAT 🏢
- **Destino**: `https://sest-senat.klubecash.com/`
- **Recursos**:
  - Benefícios exclusivos SEST SENAT
  - Saldo dedicado
  - Ofertas especiais parceiras
  - Gestão independente

---

## 🛠️ Arquivos Modificados/Criados

### 1. **Arquivo Criado**: `views/auth/wallet-select.php`
**Localização**: `/views/auth/wallet-select.php`

**Funcionalidades**:
- ✅ Verificação de sessão ativa
- ✅ Validação de permissão (`senat = 'Sim'`)
- ✅ Interface com dois cards clicáveis
- ✅ Redirecionamento via POST
- ✅ Logout integrado
- ✅ Design responsivo e moderno

**Código-chave**:
```php
// Verificar se o usuário tem senat = 'Sim'
if (!isset($_SESSION['user_senat']) || $_SESSION['user_senat'] !== 'Sim') {
    header('Location: ' . CLIENT_DASHBOARD_URL);
    exit;
}

// Processar seleção de carteira
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet'])) {
    $wallet = $_POST['wallet'];

    if ($wallet === 'klubecash') {
        header('Location: ' . CLIENT_DASHBOARD_URL);
        exit;
    } elseif ($wallet === 'senat') {
        header('Location: https://sest-senat.klubecash.com/');
        exit;
    }
}
```

---

### 2. **Arquivo Modificado**: `controllers/AuthController.php`

**Mudanças**:

**Linha ~100**: Adicionada flag para indicar necessidade de seleção de carteira
```php
$_SESSION['user_senat'] = $user['senat'] ?? 'Não';
$_SESSION['needs_wallet_selection'] = ($user['tipo'] === 'cliente' && $user['senat'] === 'Sim');
```

**Logs aprimorados**:
```php
error_log("LOGIN: Sessão básica definida - User ID: {$user['id']}, Senat: {$user['senat']}, Needs Wallet: " . ($_SESSION['needs_wallet_selection'] ? 'Yes' : 'No'));
```

---

### 3. **Arquivo Modificado**: `views/auth/login.php`

**Mudança 1 - Linha ~30**: Verificação de usuário já logado
```php
// 1. VERIFICAR SE O UTILIZADOR JÁ ESTÁ LOGADO E REDIRECIONAR
if (isset($_SESSION['user_id']) && !isset($_GET['force_login'])) {
    $userType = $_SESSION['user_type'] ?? '';
    $userSenat = $_SESSION['user_senat'] ?? 'Não';

    if ($userType == 'admin') {
        header('Location: ' . ADMIN_DASHBOARD_URL);
    } else if ($userType == 'loja' || $userType == 'funcionario') {
        header('Location: ' . STORE_DASHBOARD_URL);
    } else if ($userType == 'cliente' && $userSenat === 'Sim') {
        // Cliente com senat=Sim deve ver seleção de carteira
        header('Location: ' . SITE_URL . '/views/auth/wallet-select.php');
    } else {
        header('Location: ' . CLIENT_DASHBOARD_URL);
    }
    exit;
}
```

**Mudança 2 - Linha ~78**: Redirecionamento após login bem-sucedido
```php
// Definir URL de redirecionamento
$redirectUrl = CLIENT_DASHBOARD_URL; // padrão

// Se origem é sest-senat, redirecionar direto
if ($origem_post === 'sest-senat' && !empty($userData['senat']) && $userData['senat'] === 'Sim') {
    $redirectUrl = 'https://sest-senat.klubecash.com/';
}
// Se é cliente com senat=Sim e não veio de sest-senat, mostrar seleção de carteira
else if ($userType == 'cliente' && !empty($userData['senat']) && $userData['senat'] === 'Sim') {
    $redirectUrl = SITE_URL . '/views/auth/wallet-select.php';
}
// Redirecionamentos padrão
else if ($userType == 'admin') {
    $redirectUrl = ADMIN_DASHBOARD_URL;
} else if ($userType == 'loja' || $userType == 'funcionario') {
    $redirectUrl = STORE_DASHBOARD_URL;
}
```

---

## 📊 Estrutura do Banco de Dados

### Tabela: `usuarios`

**Campo relevante**: `senat`

```sql
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  ...
  `senat` enum('Sim','Não') DEFAULT 'Não',  -- ⭐ Campo chave
  ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Valores aceitos**:
- `'Sim'` → Usuário tem acesso ao programa SEST SENAT (mostra seleção de carteira)
- `'Não'` → Usuário comum (vai direto para dashboard normal)

---

## 🎨 Design da Página de Seleção

### Características Visuais

- **Background**: Gradiente roxo moderno (`#667eea` → `#764ba2`)
- **Cards**: Brancos com borda e hover animado
- **Ícones**: Emojis grandes e visíveis (💳 e 🏢)
- **Botões**: Gradientes personalizados:
  - Klube Cash: Laranja (`#FF7A00` → `#E86E00`)
  - SEST SENAT: Azul (`#003DA5` → `#002B75`)

### Responsividade

- **Desktop**: Cards lado a lado (2 colunas)
- **Mobile**: Cards empilhados (1 coluna)
- **Breakpoint**: 768px

---

## 🔒 Segurança

### Verificações Implementadas

1. ✅ **Sessão ativa obrigatória**
   ```php
   if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'cliente') {
       header('Location: ' . LOGIN_URL);
       exit;
   }
   ```

2. ✅ **Validação de permissão senat**
   ```php
   if (!isset($_SESSION['user_senat']) || $_SESSION['user_senat'] !== 'Sim') {
       header('Location: ' . CLIENT_DASHBOARD_URL);
       exit;
   }
   ```

3. ✅ **Proteção contra duplo submit**
   ```javascript
   let isSubmitting = false;
   document.getElementById('wallet-form').addEventListener('submit', function() {
       if (isSubmitting) {
           event.preventDefault();
           return false;
       }
       isSubmitting = true;
   });
   ```

4. ✅ **Logout seguro com limpeza de sessão e cookies**
   ```php
   $_SESSION = array();
   if (isset($_COOKIE[session_name()])) {
       setcookie(session_name(), '', time() - 3600, '/');
   }
   session_destroy();
   ```

---

## 🧪 Cenários de Teste

### ✅ Cenário 1: Cliente com senat='Sim' faz login
**Passos**:
1. Acessa `https://klubecash.com/views/auth/login.php`
2. Insere credenciais válidas (cliente com `senat = 'Sim'`)
3. Clica em "Entrar"

**Resultado esperado**:
- Redirecionado para `/views/auth/wallet-select.php`
- Vê duas opções de carteira
- Pode escolher Klube Cash OU SEST SENAT

---

### ✅ Cenário 2: Cliente com senat='Não' faz login
**Passos**:
1. Acessa `https://klubecash.com/views/auth/login.php`
2. Insere credenciais válidas (cliente com `senat = 'Não'`)
3. Clica em "Entrar"

**Resultado esperado**:
- Redirecionado DIRETO para `/cliente/dashboard`
- NÃO vê página de seleção

---

### ✅ Cenário 3: Cliente com senat='Sim' seleciona Klube Cash
**Passos**:
1. Login realizado → aparece wallet-select
2. Clica no botão "Acessar Klube Cash"

**Resultado esperado**:
- Redirecionado para `https://klubecash.com/cliente/dashboard`

---

### ✅ Cenário 4: Cliente com senat='Sim' seleciona SEST SENAT
**Passos**:
1. Login realizado → aparece wallet-select
2. Clica no botão "Acessar SEST SENAT"

**Resultado esperado**:
- Redirecionado para `https://sest-senat.klubecash.com/`

---

### ✅ Cenário 5: Login direto via SEST SENAT
**Passos**:
1. Acessa `https://klubecash.com/views/auth/login.php?origem=sest-senat`
2. Insere credenciais válidas (cliente com `senat = 'Sim'`)
3. Clica em "Entrar"

**Resultado esperado**:
- Redirecionado DIRETO para `https://sest-senat.klubecash.com/`
- NÃO passa pela tela de seleção (atalho)

---

### ✅ Cenário 6: Tentativa de acesso direto sem senat
**Passos**:
1. Cliente com `senat = 'Não'` tenta acessar diretamente:
   `https://klubecash.com/views/auth/wallet-select.php`

**Resultado esperado**:
- Redirecionado automaticamente para `/cliente/dashboard`
- Sistema bloqueia acesso não autorizado

---

### ✅ Cenário 7: Logout na página de seleção
**Passos**:
1. Está na página `/views/auth/wallet-select.php`
2. Clica no link "Sair"

**Resultado esperado**:
- Sessão destruída
- Cookies limpos
- Redirecionado para página de login

---

## 📝 Como Ativar SENAT para um Cliente

### Via Banco de Dados (SQL)

```sql
-- Ativar SENAT para um cliente específico (por email)
UPDATE usuarios
SET senat = 'Sim'
WHERE email = 'cliente@exemplo.com' AND tipo = 'cliente';

-- Ativar SENAT para um cliente específico (por ID)
UPDATE usuarios
SET senat = 'Sim'
WHERE id = 123 AND tipo = 'cliente';

-- Verificar quais clientes têm SENAT ativado
SELECT id, nome, email, senat
FROM usuarios
WHERE tipo = 'cliente' AND senat = 'Sim';
```

### Via Painel Admin (Futura Implementação)

**Sugestão de interface**:
```
┌─────────────────────────────────────────┐
│ Editar Cliente: João Silva              │
├─────────────────────────────────────────┤
│                                         │
│ Nome: [João Silva                    ] │
│ Email: [joao@exemplo.com             ] │
│ Telefone: [(34) 99999-9999           ] │
│                                         │
│ ☑ Ativar acesso SEST SENAT             │
│   (Cliente poderá escolher carteira)    │
│                                         │
│ [Salvar]  [Cancelar]                   │
└─────────────────────────────────────────┘
```

---

## 🚀 Melhorias Futuras Sugeridas

### 1. **Dashboard com Troca de Carteira**
- Adicionar botão no dashboard para trocar de carteira
- URL: `/cliente/dashboard?action=switch_wallet`

### 2. **Histórico de Acessos**
- Registrar qual carteira o cliente acessa
- Tabela: `wallet_access_logs`

### 3. **Notificações**
- Alertar cliente quando há novo benefício SEST SENAT disponível

### 4. **API de Integração**
- Endpoint para validar acesso: `/api/validate-senat-access.php`

### 5. **Painel Admin Completo**
- Interface para ativar/desativar SENAT em massa
- Exportar relatório de clientes SENAT

---

## 📞 Suporte

Para dúvidas ou problemas:
- **Logs**: Verificar `/logs/abacatepay.log` e logs do servidor
- **Email**: dev@klubecash.com
- **Documentação**: Este arquivo

---

## ✅ Checklist de Implementação

- [x] Criar página `wallet-select.php`
- [x] Modificar `AuthController.php` (linha ~100)
- [x] Modificar `login.php` (verificação inicial, linha ~30)
- [x] Modificar `login.php` (redirecionamento pós-login, linha ~78)
- [x] Adicionar validação de `senat` na sessão
- [x] Implementar logout na página de seleção
- [x] Design responsivo e moderno
- [x] Proteção contra acesso não autorizado
- [x] Documentação completa

---

**Implementado por**: Claude (AI Assistant)
**Data**: Outubro de 2025
**Versão**: 1.0

---

## 🎯 Resumo Técnico

```
FLUXO COMPLETO:
┌──────────────────────┐
│  Login (login.php)   │
└──────────┬───────────┘
           │
           ▼
    ┌──────────────┐
    │ AuthController│
    │ ::login()    │
    └──────┬───────┘
           │
           │ Define: $_SESSION['user_senat']
           │         $_SESSION['needs_wallet_selection']
           │
           ▼
    ┌─────────────────┐
    │ senat = 'Sim'?  │
    └────┬────────────┘
         │
    ┌────┴────┐
   Sim       Não
    │         │
    ▼         ▼
┌─────────┐  ┌──────────┐
│ wallet- │  │ cliente/ │
│ select  │  │ dashboard│
└────┬────┘  └──────────┘
     │
  ┌──┴───┐
  │      │
  ▼      ▼
┌────┐ ┌──────┐
│KC  │ │SENAT │
└────┘ └──────┘
```

**Arquivos-chave**:
- `views/auth/wallet-select.php` (NOVO)
- `controllers/AuthController.php` (MODIFICADO)
- `views/auth/login.php` (MODIFICADO)
- `database: usuarios.senat` (CAMPO EXISTENTE)

---

**Fim da Documentação**
