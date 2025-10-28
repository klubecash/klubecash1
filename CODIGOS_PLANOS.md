# 🎟️ Códigos de Plano - KlubeCash

Este documento lista todos os códigos de plano disponíveis para ativação por lojistas.

## 📋 Como Funciona

1. **Admin** fornece um código de plano para o lojista
2. **Lojista** acessa a página de assinatura (`/views/stores/subscription.php`)
3. **Lojista** insere o código no campo "Código de Plano"
4. **Sistema** valida o código e ativa o plano automaticamente
5. **Sistema** gera a fatura e redireciona para pagamento PIX

## ⚠️ IMPORTANTE: Planos Separados

Cada código corresponde a **UM** plano específico (mensal OU anual):
- Códigos terminados em `-M` são **exclusivamente mensais**
- Códigos terminados em `-Y` são **exclusivamente anuais**
- Não há opção de "escolher ciclo" - o código já define tudo

---

## 🟢 KLUBE START

### KLUBE-START-M (Mensal)
- **Nome**: Klube Start - Mensal
- **Preço**: R$ 149,00/mês
- **Cobrança**: Mensal recorrente
- **Trial**: 7 dias
- **Para quem**: Microempresas e MEIs (até R$ 30k/mês)
- **Recursos**:
  - Até 100 transações/mês
  - Suporte por email
  - Dashboard básico
  - Programa de fidelidade simples

### KLUBE-START-Y (Anual)
- **Nome**: Klube Start - Anual
- **Preço**: R$ 1.502,00/ano (equiv. R$ 125,17/mês)
- **Cobrança**: Anual (pagamento único)
- **Trial**: 7 dias
- **Para quem**: Microempresas e MEIs (até R$ 30k/mês)
- **Recursos**: Mesmos do plano mensal
- **Vantagem**: **Economia de 16%** (R$ 286,00/ano vs mensal)

---

## 🔵 KLUBE PLUS

### KLUBE-PLUS-M (Mensal)
- **Nome**: Klube Plus - Mensal
- **Preço**: R$ 299,00/mês
- **Cobrança**: Mensal recorrente
- **Trial**: 14 dias
- **Para quem**: Pequenas empresas (R$ 30k-100k/mês)
- **Recursos**:
  - Transações ilimitadas
  - Suporte prioritário
  - Relatórios avançados
  - Campanhas automatizadas
  - Integração API

### KLUBE-PLUS-Y (Anual)
- **Nome**: Klube Plus - Anual
- **Preço**: R$ 3.014,00/ano (equiv. R$ 251,17/mês)
- **Cobrança**: Anual (pagamento único)
- **Trial**: 14 dias
- **Para quem**: Pequenas empresas (R$ 30k-100k/mês)
- **Recursos**: Mesmos do plano mensal
- **Vantagem**: **Economia de 16%** (R$ 574,00/ano vs mensal)

---

## 🟡 KLUBE PRO

### KLUBE-PRO-M (Mensal)
- **Nome**: Klube Pro - Mensal
- **Preço**: R$ 549,00/mês
- **Cobrança**: Mensal recorrente
- **Trial**: 21 dias
- **Para quem**: Médias empresas (R$ 100k-400k/mês)
- **Recursos**:
  - **Clientes e transações ilimitados**
  - Suporte 24/7
  - Análise preditiva
  - Gamificação avançada
  - Multi-lojas
  - App customizado

### KLUBE-PRO-Y (Anual)
- **Nome**: Klube Pro - Anual
- **Preço**: R$ 5.534,00/ano (equiv. R$ 461,17/mês)
- **Cobrança**: Anual (pagamento único)
- **Trial**: 21 dias
- **Para quem**: Médias empresas (R$ 100k-400k/mês)
- **Recursos**: Mesmos do plano mensal
- **Vantagem**: **Economia de 16%** (R$ 1.054,00/ano vs mensal)

---

## ⚫ KLUBE ENTERPRISE

### KLUBE-ENTERPRISE-M (Mensal)
- **Nome**: Klube Enterprise - Mensal
- **Preço**: A partir de R$ 999,00/mês
- **Cobrança**: Mensal recorrente
- **Trial**: 30 dias
- **Para quem**: Grandes empresas (acima de R$ 400k/mês)
- **Recursos**:
  - **Tudo ilimitado**
  - **White label completo**
  - **Consultoria estratégica**
  - Gerente de conta dedicado
  - SLA premium
  - Integrações customizadas

### KLUBE-ENTERPRISE-Y (Anual)
- **Nome**: Klube Enterprise - Anual
- **Preço**: R$ 10.070,00/ano (equiv. R$ 839,17/mês)
- **Cobrança**: Anual (pagamento único)
- **Trial**: 30 dias
- **Para quem**: Grandes empresas (acima de R$ 400k/mês)
- **Recursos**: Mesmos do plano mensal
- **Vantagem**: **Economia de 16%** (R$ 1.918,00/ano vs mensal)

---

## 📊 Tabela Comparativa

| Plano | Mensal | Anual | Economia Anual |
|-------|--------|-------|----------------|
| 🟢 Start | R$ 149,00/mês | R$ 1.502,00/ano | R$ 286,00 (16%) |
| 🔵 Plus | R$ 299,00/mês | R$ 3.014,00/ano | R$ 574,00 (16%) |
| 🟡 Pro | R$ 549,00/mês | R$ 5.534,00/ano | R$ 1.054,00 (16%) |
| ⚫ Enterprise | R$ 999,00/mês | R$ 10.070,00/ano | R$ 1.918,00 (16%) |

---

## 🔐 Segurança

- ✅ Códigos únicos por plano
- ✅ Validação de código ativo
- ✅ Prevenção de duplicação de assinatura
- ✅ Geração automática de fatura
- ✅ Logs de ativação

---

## 💡 Dicas para Admins

1. **Start (🟢)**: Para microempresas iniciantes
2. **Plus (🔵)**: Para pequenas empresas em crescimento
3. **Pro (🟡)**: Para médias empresas com volume alto
4. **Enterprise (⚫)**: Para grandes empresas que precisam de tudo

**Quando usar Mensal vs Anual:**
- **Mensal (`-M`)**: Lojistas que preferem flexibilidade e pagamentos menores
- **Anual (`-Y`)**: Lojistas que querem economizar 16% e fazer compromisso

**Controle:**
- Apenas admin fornece códigos
- Lojistas podem fazer upgrade/downgrade após ativar
- Para trocar ciclo (mensal ↔ anual), usar o código equivalente

---

## 📝 Exemplos de Uso

### Exemplo 1: Microempresa Iniciante

> **Admin**: Olá! Preparamos o plano Start para você. Use o código `KLUBE-START-M` na página de assinatura.
>
> **Lojista**: Perfeito, vou começar com o mensal!
>
> *[Insere KLUBE-START-M]*
>
> **Sistema**: ✓ Plano Klube Start - Mensal ativado! Fatura: R$ 149,00. Redirecionando para PIX...

### Exemplo 2: Pequena Empresa Querendo Economizar

> **Admin**: Recomendo o Plus anual - você economiza R$ 574,00! Código: `KLUBE-PLUS-Y`
>
> **Lojista**: Ótimo, prefiro pagar anual e economizar!
>
> *[Insere KLUBE-PLUS-Y]*
>
> **Sistema**: ✓ Plano Klube Plus - Anual ativado! Fatura: R$ 3.014,00 (economia de R$ 574,00). Redirecionando para PIX...

### Exemplo 3: Empresa Fazendo Upgrade

> **Lojista**: *[Acessa painel, vê opções de upgrade]*
>
> *[Clica em "Fazer Upgrade" para Klube Pro - Mensal]*
>
> **Sistema**: Você será cobrado proporcionalmente pelo restante do período. Confirmar upgrade?
>
> *[Confirma]*
>
> **Sistema**: ✓ Upgrade realizado! Fatura proporcional gerada.

---

## 🛠️ Para Desenvolvedores

**Arquivo Principal**: [`views/stores/subscription.php`](views/stores/subscription.php)

**Ação de Resgate**: `POST` com `action=redeem_code` e `plan_code=CODIGO`

**Validação**:
```php
$codigo = strtoupper(trim($_POST['plan_code'] ?? ''));
$sqlPlano = "SELECT * FROM planos WHERE codigo = ? AND ativo = 1";
```

**Tabela**: `planos.codigo` (VARCHAR(20) UNIQUE)

**Planos Ativos**:
- 4 planos mensais (KLUBE-START-M, KLUBE-PLUS-M, KLUBE-PRO-M, KLUBE-ENTERPRISE-M)
- 4 planos anuais (KLUBE-START-Y, KLUBE-PLUS-Y, KLUBE-PRO-Y, KLUBE-ENTERPRISE-Y)

---

**Última atualização**: <?= date('d/m/Y H:i:s') ?>
