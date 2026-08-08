# Runbook de publicação e rollback

## Antes de alterar

1. Confirmar worktree e commit atual.
2. Registrar o deploy de produção em `DIARIO.md`.
3. Confirmar backup/snapshot recente do Aiven antes de migrations.
4. Executar sintaxe, testes e detector de rotas.
5. Confirmar que nenhum segredo aparece no diff.

## Publicação controlada

1. Executar deploy de produção com a CLI vinculada ao projeto.
2. Confirmar estado `Ready` antes de testar.
3. Testar domínio canônico, página inicial, login, cadastro, asset e health.
4. Testar redirecionamentos de áreas protegidas.
5. Quando a fase afetar autenticação, testar uma conta de cada papel.
6. Consultar logs e monitorar por no mínimo 30 minutos.
7. Registrar resultado e ID do novo deploy em `DIARIO.md`.

## Rollback

1. Interromper novos testes e identificar o último deploy validado.
2. Reatribuir os aliases de produção ao deploy validado usando rollback/promote da Vercel.
3. Confirmar página inicial, login e conexão ao banco.
4. Não desfazer migrations aditivas durante o incidente.
5. Registrar causa, impacto e decisão no diário.

## Banco

- Migrations devem possuir `up` idempotente.
- Não remover nem renomear coluna durante a migração.
- Antes de migration: snapshot Aiven ou dump criptografado fora do repositório.
- Nunca armazenar dump, `.env`, token ou certificado privado no Git.

## Critérios de parada

- Erro 500 novo em rota do núcleo.
- Falha de login ou perda de sessão.
- Divergência de saldo/transação.
- Exposição de segredo ou arquivo interno.
- Aumento persistente de erros nos logs.
