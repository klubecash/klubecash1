# Migração incremental do KlubeCash

Este diretório é a fonte oficial de acompanhamento da migração do KlubeCash para uma arquitetura PHP centralizada em Vercel + Aiven.

## Estados

- `Pendente`: ainda não iniciado.
- `Em andamento`: implementação ou validação em curso.
- `Validado`: implementação concluída e testada.
- `Bloqueado`: depende de credencial, decisão ou serviço externo.
- `Descartado`: removido do escopo com justificativa registrada.

## Baseline

| Item | Valor |
|---|---|
| Data do levantamento | 2026-08-08 |
| Branch | `master` |
| Commit inicial | `30fa289571eb5b3aee67a897b9e691e656c70905` |
| Deploy inicial | `dpl_GvSasfR1R8VNRYVZ9hmCpU95ngcR` |
| Domínio oficial | `https://www.klubecash.com` |
| Domínio apex | `https://klubecash.com` |
| Infraestrutura | Vercel + Aiven MySQL |
| PHP | Runtime comunitário PHP 8.5 na Vercel |
| Entradas PHP encontradas | 144 |
| Views PHP | 60 |
| APIs PHP de primeiro nível | 25 |

Variáveis existentes no início: `SITE_URL` e `DB_PASS` em Production/Preview. Valores nunca devem ser registrados nesta documentação.

## Decisões fixadas

- PHP puro centralizado, sem Laravel ou Slim.
- Não existe mais Hostinger.
- Validação em produção controlada, com deploys pequenos e rollback imediato.
- Banco Aiven atual com contas de teste identificadas.
- Preservar o núcleo completo de cashback.
- Ocultar o financeiro antigo para reconstrução futura.
- Remover pagamentos externos, Google OAuth e WhatsApp.
- Manter SMTP e consulta de CEP.
- Usar Vercel Blob para arquivos persistentes.
- Ocultar e especificar páginas inexistentes; não improvisar implementações.
- Rotacionar credenciais na fase final, mantendo o risco temporário documentado.

## Checklist mestre

### Fase 0 — Baseline e documentação

- [x] Registrar commit, deploy, domínio e variáveis existentes.
- [x] Criar documentação persistente da migração.
- [x] Inventariar estrutura principal, páginas, APIs e tabelas.
- [x] Documentar telas administrativas inexistentes.
- [x] Mapear sessões duplicadas e gravações no disco local.
- [ ] Gerar backup verificável do Aiven antes da primeira migration. `Bloqueado: requer acesso seguro ao banco/snapshot.`
- [ ] Criar contas de teste identificadas. `Pendente: executar imediatamente antes dos testes autenticados.`

### Fase 1 — Proteção operacional

- [x] Criar `/api/health` sem exposição de segredos.
- [x] Adicionar validação de sintaxe PHP.
- [x] Adicionar detector de destinos de rota inexistentes.
- [x] Adicionar verificação de segredos no código.
- [x] Criar smoke tests de produção.
- [x] Registrar deploy anterior antes de toda publicação.
- [x] Aceitar somente migrations SQL aditivas durante a migração.

### Fase 2 — Bootstrap e configuração central

- [x] Criar bootstrap único para ambiente, banco, erros, sessão e autoload.
- [x] Introduzir Composer para PSR-4 e testes.
- [ ] Remover inicializações de sessão espalhadas. `Em andamento: constantes migradas; arquivos legados ainda chamam session_start.`
- [ ] Consolidar configurações duplicadas. `Em andamento: SMTP, domínio e banco já centralizados.`
- [ ] Mover credenciais existentes para variáveis sem fallback no código. `Em andamento: banco, SMTP e JWT migrados; integrações serão removidas na Fase 6.`
- [x] Fixar host canônico e redirecionamento de `.vercel.app`.
- [x] Padronizar logs de erro sem detalhes públicos.

### Fase 3 — Roteamento

- [x] Transformar `api/vercel-router.php` em adaptador mínimo.
- [x] Criar catálogos de rotas web e API.
- [x] Declarar método, caminho, handler, nome e middleware por rota.
- [x] Padronizar 404, 405 e 500.
- [x] Redirecionar URLs PHP legadas sem perder método ou query string.
- [x] Bloquear arquivos internos e de manutenção.
- [x] Gerar inventário de rotas automaticamente.
- [ ] Remover `.htaccess` após paridade total.

### Fase 4 — Autenticação e sessões

- [ ] Criar migration aditiva para `php_sessions`.
- [ ] Implementar `SessionHandlerInterface` com Aiven.
- [ ] Centralizar configuração segura do cookie.
- [ ] Rotacionar ID da sessão em eventos sensíveis.
- [ ] Centralizar middleware por papel de usuário.
- [ ] Adicionar CSRF às operações autenticadas.
- [ ] Validar sessão entre múltiplas instâncias serverless.

### Fase 5 — Núcleo funcional

- [ ] Preservar site público, cadastro, login e recuperação.
- [ ] Preservar clientes, lojas, funcionários, carteiras e perfis.
- [ ] Preservar transações de cashback, saldos e extratos.
- [ ] Corrigir imports relativos e dependências ausentes.
- [ ] Corrigir páginas administrativas existentes.
- [ ] Ocultar telas inexistentes e módulos financeiros antigos.
- [ ] Preservar dados financeiros históricos sem expor telas quebradas.
- [ ] Desativar blog incompleto ou fornecer fallback estático.

### Fase 6 — APIs e integrações

- [ ] Centralizar autenticação, autorização, CORS e erros das APIs.
- [ ] Declarar métodos HTTP permitidos.
- [ ] Preservar contratos JSON usados pelo frontend atual.
- [ ] Remover Mercado Pago, OpenPix, AbacatePay, Stripe, Google e WhatsApp.
- [ ] Remover seus botões, scripts, rotas, webhooks e dependências.
- [ ] Manter dados históricos externos como somente leitura.
- [ ] Manter SMTP e CEP com timeout e fallback.

### Fase 7 — Arquivos persistentes

- [ ] Criar Blob público para logos.
- [ ] Criar Blob privado para documentos futuros.
- [ ] Implementar gateway Node mínimo com `@vercel/blob`.
- [ ] Proteger PHP → gateway com HMAC e expiração.
- [ ] Criar migration aditiva para `arquivos`.
- [ ] Validar MIME, extensão, tamanho e autorização.
- [ ] Migrar arquivos recuperáveis e oferecer placeholder para ausentes.
- [ ] Eliminar gravação em disco local da função.

### Fase 8 — Logs, tarefas e limpeza

- [ ] Trocar logs em arquivo por logs estruturados.
- [ ] Desativar crons financeiros antigos.
- [ ] Definir processamento da fila de e-mail.
- [ ] Excluir workflow FTP.
- [ ] Excluir scripts de debug e manutenção do artefato publicado.
- [ ] Remover duplicações somente após confirmar ausência de referências.

### Fase 9 — Testes e aceite

- [ ] Adicionar testes unitários e de integração.
- [ ] Testar autenticação e isolamento por papel.
- [ ] Testar o núcleo de cashback.
- [ ] Testar rotas novas, legadas e bloqueadas.
- [ ] Testar Blob público/privado e uploads inválidos.
- [ ] Confirmar remoção de integrações e telas ocultadas.
- [ ] Executar smoke test e monitorar cada deploy.

### Fase 10 — Segurança final

- [ ] Rotacionar banco, SMTP, JWT, Vercel, Blob e segredo interno.
- [ ] Revogar credenciais de integrações removidas.
- [ ] Confirmar ausência de segredos em código e artefatos.
- [ ] Validar DNS, SSL, domínio canônico e redirecionamentos.
- [ ] Registrar versão final, migrations e variáveis obrigatórias.
- [ ] Abrir planejamento separado para o novo financeiro.

## Critério global de conclusão

A migração só termina quando o núcleo de cashback passar pelos testes de visitante, cliente, loja, funcionário e administrador; nenhuma rota chamar integrações removidas; sessões e arquivos forem persistentes; e o deploy puder ser revertido de forma documentada.
