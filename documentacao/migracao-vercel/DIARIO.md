# Diário da migração

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
