# Integração Klube Pass → KlubeCash

O Klube Pass (eventos) usa o KlubeCash como carteira oficial. Esta página descreve o que o KlubeCash oferece ao Pass e como ligar em produção.

## Regras de dinheiro

- **Loja promotora** (`lojas.tipo_parceiro = 'promotor_eventos'`) não gera reembolso de saldo. O dinheiro das sobras já está com ela.
- **Sobra de evento** vira um lote próprio (`cashback_creditos.kind = 'pass_leftover'`, movimento `credito_sobra_evento`). Ela nunca gera giftback.
- **Giftback do promotor** é calculado só sobre a parte paga em dinheiro ou Pix, e só se `lojas.giftback_sobre_dinheiro = 1`. A parte paga com saldo nunca gera giftback.
- **Vendas no promotor** são aceitas mesmo com `cashback_ativo = 0`.
- **Validade:** cada sobra pode ter validade própria (`valid_until`). Sem ela, vale a política da loja (`giftback_expiration_days`).
- **Saldo nunca fica negativo**, e repetir uma operação não duplica nada. Os testes em `tests/php/pass_integration_integration.php` cobrem as regras 1, 2, 3, 4, 7 e 8 do plano.

## API de parceiro

Base: `https://klubecash.com/api/v2/partner/`. É servidor a servidor, sem sessão nem cookie.

**Cabeçalhos**
- `Authorization: Bearer kcp_<id>_<segredo>`: chave emitida por parceiro, com permissões exatas. O curinga `*` nunca vale.
- `Idempotency-Key` (ou `X-Idempotency-Key`): obrigatória em todo POST, de 16 a 128 caracteres `[A-Za-z0-9._:-]`.
  - Mesma chave e mesmo corpo devolvem a mesma resposta.
  - Mesma chave com outro corpo dá 409.
- `Content-Type: application/json`.

**Identificadores**
- `organizer_id` é o `pass_organizer_id` da loja promotora, configurado pelo admin no modal da loja.
- `pass_ref` identifica a operação no Pass: de 8 a 80 caracteres `[A-Za-z0-9._-]`. É único no KlubeCash inteiro e serve de segunda trava contra duplicidade.

| Rota | Permissão | Corpo / query | Resposta `data` |
|---|---|---|---|
| `GET pass/balance` | `pass:balance` | `?klubecash_user_id=&organizer_id=` | `balance_cents`, `next_expiration_date`, `next_expiration_cents`, `giftback_on_cash`, `giftback_percentage` |
| `POST pass/debit` | `pass:debit` | `klubecash_user_id`, `organizer_id`, `amount_cents`, `cash_cents?`, `pass_ref`, `description?` | `debited_cents`, `giftback_cents`, `balance_cents`, `replayed` |
| `POST pass/credit` | `pass:credit` | `organizer_id`, `event_name?`, `items[]` (até 500: `klubecash_user_id`, `amount_cents`, `pass_ref`, `valid_until?` AAAA-MM-DD) | `credited`, `replayed`, `rejected`, `credited_cents`, `items[]` com `status` credited / replayed / rejected |
| `POST pass/reverse` | `pass:reverse` | `pass_ref`, `reason` (5 a 200) | `type` (debit / leftover_credit), `reversed_cents`, `balance_cents` |

**Detalhes**
- **`cash_cents` no débito** é a parte da mesma recarga paga em dinheiro ou Pix. Ela só serve para calcular o giftback.
- **Lote recusado:** o crédito em lote processa cada item na própria transação. Um item recusado (cliente inativo, `pass_ref` de outra operação) não impede os outros.
  - A resposta guardada para aquela `Idempotency-Key` inclui os itens recusados.
  - Para tentar de novo, mande só os recusados com outra chave.
- **Estorno de débito:** devolve o saldo aos mesmos lotes, que mantêm a validade original. Também revoga o giftback daquela recarga, se ele ainda não tiver sido usado.
- **Estorno de sobra:** só tira o que ainda não foi usado. Se a sobra já foi usada, a resposta é 409 e o caso vai para revisão manual.

**Erros:** `{ "success": false, "error": { "code", "message" }, "request_id" }`.

| Status | Quando |
|---|---|
| 400 | Falta a `Idempotency-Key` |
| 401 | Chave inválida, revogada ou expirada |
| 403 | Falta a permissão |
| 404 | Organizador sem loja ligada, cliente inexistente ou inativo |
| 409 | Saldo insuficiente, `pass_ref` de outra operação, corpo diferente para a mesma chave |
| 422 | Dados inválidos |
| 429 | Limite diário atingido |

**Limites**
- Por chave: requisições por dia, e centavos de débito e de crédito por dia. Zero em débito ou crédito significa sem teto.
- Por cliente: `PASS_USER_DAILY_DEBIT_LIMIT_CENTS` (padrão R$ 2.000 por dia).
- Os tetos são travados na mesma transação do débito.

**Log:** a tabela `pass_integration_log` guarda uma linha por chamada, só com ids, valores, status, `pass_ref`, o hash da `Idempotency-Key` e o HMAC do IP. Nunca guarda corpo, nome, telefone ou IP puro.

## Avisos ao cliente

Com `PASS_CREDIT_NOTICES_ENABLED=true`, cada sobra creditada enfileira um aviso em `pass_credit_notices`, nos canais WhatsApp e e-mail. O processador `/api/internal/notifications` trata essa fila:
- O WhatsApp sai entre 9h e 20h, com até 5 tentativas. Uma entrega incerta nunca é reenviada.
- O e-mail entra na `email_queue` existente.

O worker da `email_queue` (`scripts/cron/process-email-queue.php`) ainda não está agendado na VPS e depende de `EMAIL_DELIVERY_ENABLED=true`.

## Cadastro vindo do Pass

O “Criar conta” do Pass leva a `/registro?voltar=<url do Pass>`. Depois do cadastro, o `/login` mostra “volte ao Klube Pass”. Só origens listadas em `PASS_RETURN_ORIGINS` são aceitas (padrão `https://pass.grupoklube.com`).

## Ligar em produção (passos do responsável, nesta ordem)

1. Backup do banco do KlubeCash.
2. Migração:
   - Simule com `php database/migrations/run_pass_integration_migration.php` e confira a lista de mudanças.
   - Aplique com `... --apply`.
   - Para desativar a chave antiga `kc_live` com `*`, rode `... --apply --deactivate-legacy-wildcard-keys`.
3. Deploy do código. Ele funciona antes da migração: sem as colunas, toda loja é comum.
4. No admin, abra a loja do promotor, marque “Promotor de eventos do Klube Pass”, cole o ID do organizador e decida o giftback sobre dinheiro.
5. Emita a chave do Pass no terminal do contêiner PHP:
   ```
   php scripts/partner/issue-key.php --partner="Klube Pass" \
     --permissions=pass:balance,pass:debit,pass:credit,pass:reverse \
     --daily-requests=20000 --daily-debit-cents=5000000 --daily-credit-cents=20000000
   ```
   A chave aparece uma vez. Cole direto no ambiente do Pass (`KLUBECASH_PARTNER_KEY`). Para revogar: `UPDATE partner_api_keys SET is_active=0, revoked_at=NOW() WHERE public_id='...'`.
6. Opcional: `KLUBCASH_PASS_CREDIT_NOTICES_ENABLED=true` e reimplantar.

**Pepper das chaves:** com `VPS_DERIVE_RUNTIME_KEYS=true` (padrão da VPS), o pepper deriva do `JWT_SECRET`. Trocar o `JWT_SECRET` invalida todas as chaves de parceiro.
