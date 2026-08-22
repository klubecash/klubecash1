# KlubeCashNew

Frontend do Klube Cash reconstruído progressivamente em Next.js 16 e React 19. O App Router atende a área pública, autenticação, área lojista e Admin Master, preservando sessão e regras de negócio no backend PHP.

## Desenvolvimento local

O PHP deve estar disponível em `http://127.0.0.1:8000`:

```powershell
./scripts/start-local.ps1
```

Em outro terminal, inicie o Next:

```powershell
cd KlubeCashNew
npm install
npm run dev
```

O frontend ficará em `http://127.0.0.1:3000`. As rotas migradas são:

- `/` — homepage;
- `/login` — autenticação, via ponte `POST /api/auth/login` para o PHP;
- `/registro` — cadastro de cliente, via ponte `POST /api/auth/register` para o PHP;
- `/recuperar-senha` — solicitação e redefinição de senha, preservando token, CSRF, e-mail e revogação de sessões do PHP.
- `/store/*` — operação da loja no modelo de cashback por assinatura;
- `/admin/*` — Admin Master completo, incluindo usuários, lojas, transações, financeiro legado, relatórios, assinaturas, planos, marketing, templates, configurações e auditoria.

Assets e rotas legadas ainda são encaminhados para o PHP na porta 8000. `STORE_UI_MODE`, `ADMIN_UI_MODE`, `STORE_LEGACY_ROUTES` e `ADMIN_LEGACY_ROUTES` permitem rollback total ou seletivo.

Para usar outro endereço do backend, copie `.env.local.example` para `.env.local` e ajuste `PHP_BACKEND_URL`.

## Validação

```powershell
npm run typecheck
npm run lint
npm test
npm run test:e2e
npm run build
```

O E2E usa Playwright em desktop, tablet e celular, nos temas claro e escuro. Ele cria apenas uma sessão administrativa temporária em arquivo, não altera registros do banco e destrói a sessão ao final.

As APIs administrativas e lojistas usam contratos v2, `no-store`, CSRF, idempotência nas mutações sensíveis e encaminhamento de cookie pelo BFF do Next. Erros de conexão nunca são convertidos em indicadores zerados.

## Deploy

Esta pasta não modifica a configuração atual de produção. A união do serviço Next com o runtime PHP no mesmo domínio será configurada somente na etapa específica de deploy para a Vercel.
