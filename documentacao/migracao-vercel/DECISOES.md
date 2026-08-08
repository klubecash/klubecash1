# Registro de decisões

## ADR-001 — PHP centralizado

**Estado:** Aceita.

Manter PHP puro e migrar gradualmente para Kernel, router, middleware e serviços centrais. Laravel e Slim ficam fora do escopo para reduzir regressões.

## ADR-002 — Produção controlada

**Estado:** Aceita.

Não haverá staging. Cada entrega deve ser pequena, testada localmente, publicada com registro do deploy anterior e monitorada. Falha implica rollback imediato.

## ADR-003 — Banco atual para testes

**Estado:** Aceita com risco.

Usar contas identificadas no Aiven de produção. Não executar pagamentos reais; migrations somente aditivas; operações de teste devem ser reversíveis.

## ADR-004 — Remoção de integrações

**Estado:** Aceita.

Remover Mercado Pago, OpenPix, AbacatePay, Stripe, Google OAuth e WhatsApp. Preservar dados históricos. Manter SMTP e consulta de CEP.

## ADR-005 — Financeiro futuro

**Estado:** Aceita.

Ocultar assinaturas, faturas, comissões e pagamentos atuais. Não apagar tabelas. A reconstrução será planejada separadamente.

## ADR-006 — Persistência

**Estado:** Aceita.

Sessões ficam no Aiven em tabela própria. Logos usam Blob público; documentos usam Blob privado. Nenhum dado durável pode depender do filesystem da função.

## ADR-007 — Segredos

**Estado:** Aceita com risco temporário.

Mover segredos para variáveis durante a migração e rotacioná-los ao final, conforme decisão do proprietário. Credenciais removidas devem ser revogadas.
