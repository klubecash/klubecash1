# Diário da migração

## 2026-10-05 — Central de redes, filiais, equipe e vendas

- **Estado:** Publicado; smoke test público aprovado, validação autenticada pendente.
- **Deploy anterior:** `dpl_9112jY4yuJx8jGfg6FY5uKeXmqCf` em `https://www.klubecash.com`.
- **Deploy atual:** `dpl_BZugXDGhRGKu89jjX1LzR9xheDMQ`, `Ready`, associado a `https://www.klubecash.com`.
- **Alterações:** central de redes para admin e lojista, filtros compartilhados e diagnóstico de carteiras, junto das alterações locais existentes.
- **Migrações:** nova tabela de auditoria de reparos ainda não aplicada; o reparo permanece bloqueado até instalação e reconciliação verificadas.
- **Testes locais:** home/login HTTP 200; centrais protegidas redirecionam sem sessão; 56 testes Next, lint, TypeScript, build, 250 arquivos PHP, 104 rotas e detector de segredos aprovados.
- **Produção:** home, login, cadastro e health HTTP 200; banco `ok`; centrais sem sessão redirecionam; APIs protegidas retornam 401; migração não exposta por HTTP (404).
- **Incidente de deploy:** primeira tentativa falhou porque o registro de contêineres atingiu 50 imagens; removida somente a imagem sem tag `image_fWUT2f25IH9oqpiwe0DBF0QGiB7l` de 44 dias atrás. Imagem ativa e deploy anterior preservados. Segundo deploy concluído.
- **Próximo passo:** ensaiar e aplicar a migração de auditoria em banco isolado antes de ativar reparos; validar fluxos com contas de teste autenticadas.

## 2026-10-02 — Correção do login de lojas e funcionários

- **Estado:** esquema de acesso aplicado no banco `default`; deploy final promovido.
- **Deploy anterior:** `dpl_AKZfxR3567GfBrgKrXLZpGMWNVr3`.
- **Deploy final:** `dpl_9112jY4yuJx8jGfg6FY5uKeXmqCf` em `https://www.klubecash.com`.
- **Causa:** `store_user_memberships` e demais objetos de rede estavam ausentes no banco usado pela produção, gerando SQLSTATE 42S02 no login.
- **Migração:** `run_store_network_migration.php --apply-schema-only` executada em deploy protegido; criou tabelas/colunas e importou vínculos antigos, sem alterar saldos ou criar redes. Simulação posterior retornou `changes: []`.
- **Conciliação pendente:** zero diferenças nas carteiras existentes, mas 9 créditos sem carteira (R$ 290,50 disponíveis nas lojas 38 e 88). Entrada e retomada de filiais em rede estão bloqueadas até revisão desses registros.
- **Segurança:** ponte temporária de migração removida do deploy final (HTTP 404). Erros SQL deixam de ser exibidos na resposta de login.
- **Verificação:** health e banco `ok`; login fictício retorna credenciais inválidas sem erro SQL; home/login HTTP 200; áreas protegidas exigem sessão. Não foi usado login de conta real.
- **Git:** deploy direto pela CLI; alterações locais ainda não commitadas nem enviadas ao GitHub.

## 2026-09-30 — Publicação integral e validade individual do giftback

- **Estado:** Publicado; verificações de produção aprovadas.
- **Deploy anterior:** `dpl_GBTKnYNZaUKa1qD5pBj1URbZ6LZJ`.
- **Deploy final:** `dpl_6r3NB7vL5MupyBRaoZ3b9aNo5Pfc`, promovido para `https://www.klubecash.com`.
- **Escopo:** todas as alterações locais, incluindo giftback, cobrança e visual, autorizado pelo usuário.
- **Backup:** dispensado explicitamente pelo usuário, que informou ausência de uso do ambiente.
- **Ativação:** conferir schema no próprio ambiente, pausar movimentações, aplicar migrations aditivas e reconciliar saldos antes de liberar.
- **Agendamento:** plano Vercel Hobby; cron diário, com vencimentos também verificados em consultas e utilizações. A execução a cada minuto depende de um agendador externo.
- **Acesso operacional:** credencial temporária exclusiva para esta ativação, com prazo de expiração; ponte de migração removida da versão final.
- **Migrations:** admin/store/billing v2 já estavam instaladas; aplicados os campos/tabelas aditivos de checkout transparente e o controle individual de giftback. Nenhuma assinatura externa foi cancelada.
- **Reconciliação:** 10 carteiras e 10 créditos de abertura; R$ 559,49 preservados; `ready=1`; zero divergências. Nenhuma loja recebeu prazo automaticamente.
- **Verificação:** home/login/cadastro/asset/health HTTP 200; áreas protegidas redirecionam para login; API administrativa e worker sem autorização retornam 401; arquivos internos e ponte temporária retornam 404.
- **Worker:** execução manual pelo agendador em `2026-09-30T08:07:00Z`, resposta HTTP 200, `failed=0`, sem valores vencidos pendentes.
- **Limitação:** não houve teste autenticado com conta real nem alterações de prazo em lojas reais durante o deploy. O agendamento por minuto continua dependente de worker externo; na Vercel Hobby foi instalado o diário.
- **Git:** publicação direta pela CLI, sem push que pudesse disparar o workflow legado de FTP/Hostinger.

## 2026-08-08 — Início

- **Estado:** Em andamento.
- **Commit inicial:** `30fa289571eb5b3aee67a897b9e691e656c70905`.
- **Deploy inicial:** `dpl_GvSasfR1R8VNRYVZ9hmCpU95ngcR`.
- **Produção:** `https://www.klubecash.com`.
- **Alteração:** criação da documentação persistente e registro do baseline.
- **Banco:** nenhuma alteração.
- **Deploy:** ainda não realizado para esta etapa.
- **Próximo passo:** implementar proteções automatizadas da Fase 1.

## 2026-08-08 — Fase 1: proteção operacional

- **Estado:** Validado.
- **Deploy anterior:** `dpl_GvSasfR1R8VNRYVZ9hmCpU95ngcR`.
- **Alterações:** health check, lint PHP, verificação de rotas, scanner de segredos, smoke test e `.vercelignore`.
- **Migrations:** nenhuma.
- **Testes executados:** 144 arquivos PHP sem erro de sintaxe; 68 destinos do router existentes.
- **Risco encontrado:** credenciais literais em configurações; valores omitidos e rotação mantida para a fase final conforme decisão registrada.
- **Próximo passo:** deploy controlado e smoke test no domínio oficial.

## 2026-08-08 — Fase 2: bootstrap central

- **Estado:** Validado.
- **Deploy anterior:** `dpl_8H1BKVAHA9fJd5eSmETSb5oe89vh`.
- **Deploy validado:** `dpl_DikGj1VRvg9s7a8MXfXv6DrHKSHW`.
- **Alterações:** bootstrap único, contexto de requisição, logger estruturado, autoload PSR-4, Composer, configuração central de sessão e carregamento preguiçoso do banco.
- **Configuração externa:** SMTP e JWT copiados para variáveis protegidas; `SITE_URL` normalizada para `https://www.klubecash.com`.
- **Migrations:** nenhuma.
- **Testes executados:** Composer validado, lint PHP, rotas e renderização local do login.
- **Resultado:** página pública, autenticação, assets, áreas protegidas e health check aprovados em produção.
- **Rollback necessário:** Não.
- **Próximo passo:** roteamento centralizado.

## 2026-08-08 — Fase 3: roteamento centralizado

- **Estado:** Validado.
- **Deploy anterior:** `dpl_DikGj1VRvg9s7a8MXfXv6DrHKSHW`.
- **Deploy validado:** `dpl_HUVr1s79BTXkFyFSc189v6PjZqdc`.
- **Alterações:** adaptador Vercel mínimo, Kernel, Router, catálogo web/API, rotas dinâmicas, respostas 404/405/500, redirects 308 das URLs PHP legadas e inventário automático.
- **Migrations:** nenhuma.
- **Testes executados:** lint de todos os arquivos PHP; 86 rotas com destinos válidos; rotas fixas e dinâmicas; preservação de query e método em redirect; bloqueio de configuração; 404 e 405 web/JSON.
- **Resultado:** smoke test completo aprovado no domínio oficial; rotas dinâmica e legada, 404 e 405 aprovados em produção.
- **Rollback necessário:** Não.
- **Próximo passo:** eliminar inicializações duplicadas e implementar sessão persistente.

## Modelo para próximas entradas

### AAAA-MM-DD — Título

- **Estado:** Pendente/Em andamento/Validado/Bloqueado/Descartado.
- **Commit anterior:**
- **Deploy anterior:**
- **Alterações:**
- **Migrations:**
- **Testes executados:**
- **Resultado:**
- **Rollback necessário:** Não/Sim, com justificativa.
- **Próximo passo:**
