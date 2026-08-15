# KlubeCashNew

Frontend do Klube Cash reconstruído progressivamente em Next.js 16 e React 19. O App Router já atende a homepage, o login e o cadastro de clientes, preservando sessão, autenticação e regras de negócio do backend PHP existente.

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

Rotas ainda não implementadas no Next, como `/assets/*` e os dashboards, continuam encaminhadas para o PHP na porta 8000.

Para usar outro endereço do backend, copie `.env.local.example` para `.env.local` e ajuste `PHP_BACKEND_URL`.

## Validação

```powershell
npm run typecheck
npm run lint
npm test
npm run build
```

O endpoint PHP `GET /api/homepage-context` é somente leitura, privado, sem cache e não retorna IDs, e-mails ou outros dados sensíveis. As pontes de login, cadastro e recuperação usam `no-store`, preservam os cookies HTTP do backend e não registram credenciais ou tokens em logs do Next.

## Deploy

Esta pasta não modifica a configuração atual de produção. A união do serviço Next com o runtime PHP no mesmo domínio será configurada somente na etapa específica de deploy para a Vercel.
