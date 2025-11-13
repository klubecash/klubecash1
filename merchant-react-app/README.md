# Klube Cash - Sistema de Lojistas (React)

Sistema de gerenciamento completo para lojistas parceiros do Klube Cash, desenvolvido em React.

## 📋 Índice

- [Sobre o Projeto](#sobre-o-projeto)
- [Tecnologias](#tecnologias)
- [Pré-requisitos](#pré-requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Execução](#execução)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Funcionalidades](#funcionalidades)
- [Build e Deploy](#build-e-deploy)

## 🚀 Sobre o Projeto

Este projeto é a refatoração completa do sistema de gerenciamento de lojistas do Klube Cash, migrando de PHP puro para React. O sistema mantém toda a funcionalidade existente enquanto melhora significativamente a experiência do usuário e a manutenibilidade do código.

### Principais Melhorias

- ✅ Interface moderna e responsiva
- ✅ Experiência de usuário aprimorada
- ✅ Código componentizado e reutilizável
- ✅ Gerenciamento de estado eficiente
- ✅ Performance otimizada
- ✅ Fácil manutenção e escalabilidade

## 🛠 Tecnologias

- **React 18** - Biblioteca JavaScript para construção de interfaces
- **React Router v6** - Navegação entre páginas
- **Axios** - Cliente HTTP para requisições à API
- **Tailwind CSS** - Framework CSS utilitário
- **Chart.js** - Biblioteca para gráficos
- **React Hook Form** - Gerenciamento de formulários
- **Zod** - Validação de esquemas
- **date-fns** - Manipulação de datas

## 📦 Pré-requisitos

Antes de começar, certifique-se de ter instalado:

- Node.js (v14 ou superior)
- npm ou yarn
- Git

## 💿 Instalação

1. Clone o repositório (se ainda não fez):

```bash
cd klubecash1/merchant-react-app
```

2. Instale as dependências:

```bash
npm install
```

## ⚙️ Configuração

1. Copie o arquivo `.env.example` para `.env`:

```bash
cp .env.example .env
```

2. Configure as variáveis de ambiente no arquivo `.env`:

```env
REACT_APP_API_URL=https://klubecash.com/api
REACT_APP_SITE_URL=https://klubecash.com
REACT_APP_DEFAULT_PAGE_SIZE=20
```

3. Ajuste as URLs conforme seu ambiente (desenvolvimento/produção).

## 🚀 Execução

### Modo Desenvolvimento

```bash
npm start
```

A aplicação estará disponível em `http://localhost:3000`

### Modo Produção

```bash
npm run build
```

Os arquivos otimizados serão gerados na pasta `build/`

### Testes

```bash
npm test
```

## 📁 Estrutura do Projeto

```
merchant-react-app/
├── public/                 # Arquivos estáticos
├── src/
│   ├── components/         # Componentes React
│   │   ├── common/         # Componentes reutilizáveis
│   │   ├── layout/         # Layout (Sidebar, Header)
│   │   ├── dashboard/      # Componentes do Dashboard
│   │   ├── transactions/   # Componentes de Transações
│   │   ├── payments/       # Componentes de Pagamentos
│   │   ├── subscriptions/  # Componentes de Assinaturas
│   │   ├── profile/        # Componentes de Perfil
│   │   └── employees/      # Componentes de Funcionários
│   │
│   ├── pages/              # Páginas da aplicação
│   ├── hooks/              # Custom hooks
│   ├── context/            # Context API
│   ├── services/           # Serviços de API
│   ├── utils/              # Funções utilitárias
│   ├── styles/             # Estilos globais
│   ├── App.jsx             # Componente raiz
│   └── index.js            # Entry point
│
├── .env                    # Variáveis de ambiente
├── package.json            # Dependências e scripts
└── tailwind.config.js      # Configuração do Tailwind
```

## 🎯 Funcionalidades

### Dashboard
- Visualização de estatísticas de vendas
- Gráfico de vendas mensais
- Transações recentes
- Comissões pendentes

### Transações
- Registro de novas transações
- Lista de transações com filtros
- Busca de clientes
- Upload em lote (CSV)

### Pagamentos
- Solicitação de pagamentos
- Histórico de pagamentos
- Geração de QR Code PIX
- Acompanhamento de status

### Assinaturas
- Visualização do plano atual
- Comparação de planos
- Upgrade/downgrade
- Resgate de códigos promocionais

### Perfil da Loja
- Edição de informações
- Upload de logo
- Gerenciamento de endereço
- Configuração de cashback

### Funcionários
- Lista de funcionários
- Adicionar/editar funcionários
- Definição de cargos
- Controle de acesso

## 🏗 Build e Deploy

### Build de Produção

```bash
npm run build
```

### Deploy

1. Os arquivos gerados na pasta `build/` devem ser copiados para o servidor
2. Configure o servidor web (Apache/Nginx) para servir a aplicação
3. Certifique-se de que as rotas estão configuradas corretamente (SPA)

### Exemplo de configuração Apache (.htaccess):

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

## 🔐 Autenticação

A autenticação é feita através da página de login PHP existente (`/views/auth/login.php`). Após o login:

1. O PHP gera um JWT token
2. O token é armazenado em cookie e localStorage
3. O React valida o token em cada requisição
4. Se o token expirar, o usuário é redirecionado para login

## 📚 Documentação Adicional

Para mais informações sobre o planejamento e arquitetura, consulte:

- `PLANEJAMENTO_REACT_LOJISTA.md` - Planejamento completo do sistema
- `/tmp/merchant_system_analysis.md` - Análise do sistema atual
- `/tmp/react_rewrite_guide.md` - Guia de implementação

## 👥 Contato

Para dúvidas ou sugestões, entre em contato com a equipe de desenvolvimento.

## 📄 Licença

Este projeto é propriedade do Klube Cash. Todos os direitos reservados.
