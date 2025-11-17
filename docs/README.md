# 📚 Documentação Backend Klubecash

Bem-vindo à documentação completa do backend da plataforma Klubecash!

## 🎯 Sobre Este Projeto

A Klubecash é uma plataforma de cashback e gestão de transações financeiras que conecta lojistas e consumidores através de um sistema de recompensas e comissões.

## 📖 Índice da Documentação

### 1. Fundamentos
- **[[01-visao-geral]]** - Visão geral do sistema e conceitos principais
- **[[02-arquitetura]]** - Arquitetura técnica e estrutura do código

### 2. APIs e Integrações
- **[[03-apis-endpoints]]** - Documentação completa de todas as APIs
- **[[04-banco-de-dados]]** - Estrutura do banco de dados e tabelas
- **[[05-integracoes]]** - Integrações externas (pagamentos, WhatsApp, email)

### 3. Segurança e Desenvolvimento
- **[[06-autenticacao-seguranca]]** - Autenticação, autorização e segurança
- **[[07-fluxos-negocio]]** - Fluxos principais da aplicação
- **[[08-guia-desenvolvimento]]** - Guia para desenvolvedores

## 🚀 Início Rápido

### Acesso aos Ambientes

- **Produção**: https://klubecash.com
- **Banco de Dados**: MySQL (klube_cash)
- **Servidor**: Linux 4.4.0

### Tecnologias Principais

- **Backend**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Arquitetura**: MVC (Model-View-Controller)
- **Autenticação**: JWT + Sessions
- **APIs de Pagamento**: Mercado Pago, Stripe, Abacate Pay, OpenPix

## 📊 Estatísticas do Projeto

- **159 arquivos PHP** no backend
- **54 tabelas** no banco de dados
- **24 endpoints** de API documentados
- **6 integrações** externas ativas
- **9 controllers** principais
- **7 models** de dados

## 🔑 Funcionalidades Principais

1. **Sistema de Cashback Distribuído**
   - Gestão de carteiras digitais
   - Distribuição automática de comissões
   - Histórico completo de transações

2. **Gestão de Lojas e Lojistas**
   - Cadastro e aprovação de lojas
   - Sistema de comissões personalizadas
   - Funcionários e permissões

3. **Assinaturas e Planos**
   - Planos mensais e anuais
   - Upgrade proporcional de planos
   - Renovação automática

4. **Pagamentos Múltiplos**
   - PIX (Mercado Pago, Abacate Pay, OpenPix)
   - Cartão de crédito (Mercado Pago, Stripe)
   - Webhooks de confirmação

5. **Sistema SEST SENAT**
   - Seleção de carteiras específicas
   - Gerenciamento de benefícios

## 🛠️ Para Desenvolvedores

### Requisitos
```bash
- PHP >= 7.4
- MySQL >= 5.7
- Composer
- Apache/Nginx com mod_rewrite
```

### Configuração Rápida
```bash
# Clonar repositório
git clone [repo-url]

# Configurar banco de dados
cp config/database.example.php config/database.php
# Editar credenciais em config/database.php

# Configurar constantes
cp config/constants.example.php config/constants.php
# Adicionar API keys

# Rodar servidor local
php -S localhost:8000
```

## 📞 Suporte e Contato

Para dúvidas ou sugestões sobre esta documentação, entre em contato com a equipe de desenvolvimento.

## 🔄 Última Atualização

**Data**: 2025-11-17
**Versão**: 1.0.0
**Status**: Documentação completa inicial

---

## 🗺️ Navegação Rápida

### Por Funcionalidade
- [Autenticação e Login](03-apis-endpoints.md#autenticação)
- [Transações Financeiras](07-fluxos-negocio.md#transações)
- [Gestão de Lojas](07-fluxos-negocio.md#lojas)
- [Sistema de Pagamentos](05-integracoes.md#pagamentos)

### Por Tipo de Usuário
- **Desenvolvedor Backend**: Comece por [[02-arquitetura]] e [[08-guia-desenvolvimento]]
- **Desenvolvedor Frontend**: Veja [[03-apis-endpoints]] para integração
- **DevOps**: Consulte [[06-autenticacao-seguranca]] e requisitos técnicos
- **Produto/Negócio**: Leia [[01-visao-geral]] e [[07-fluxos-negocio]]

---

**Nota**: Esta documentação foi criada para ser acessada via Obsidian ou qualquer leitor Markdown. Os links em `[[formato]]` funcionam nativamente no Obsidian.
