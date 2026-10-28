# Validade individual do giftback

O prazo é configurado em **Admin → Lojas parceiras → Editar → Validade do giftback**.
Somente administradores ativos podem definir de 1 a 3650 dias ou escolher sem vencimento.
A regra vale para novos créditos, a partir da liberação efetiva, mesmo quando a compra
é registrada com uma data anterior. Alterar ou desativar a regra não muda os créditos existentes.

Um crédito liberado em 20/01 com 30 dias vale até 19/02, inclusive. O servidor armazena
o limite exclusivo de 20/02 às 00:00 em `America/Sao_Paulo`. Na utilização, os créditos
que vencem antes são consumidos primeiro; os sem prazo ficam por último.

Em **Créditos e validades**, selecione uma loja, um cliente e um crédito. Prorrogar exige
uma data posterior e motivo. Reativar exige selecionar a expiração individual, data
futura e motivo: somente a parcela expirada desse cliente volta. Nenhuma ação em massa
é oferecida. Expirações de créditos cancelados não podem ser reativadas.

O cliente vê o próximo vencimento, os créditos e seu histórico na carteira e no extrato,
inclusive depois de zerar. O histórico exportado inclui os eventos dos créditos; motivos
internos e identificação do administrador não são expostos ao cliente.

## Banco e ativação

A implementação depende das migrations anteriores do admin v2 (auditoria, idempotência
e `updated_at`) e store v2. O código não cria tabelas automaticamente nas requisições.

1. Faça backup do banco. Coloque os endpoints financeiros em manutenção no proxy/servidor
   e interrompa workers que aprovem ou movimentem saldo. Aguarde requisições em andamento.
2. Publique juntos o backend e frontend novos. `GIFTBACK_WRITES_PAUSED=1` também bloqueia
   acessos pelo novo motor durante o corte; não substitui parar escritores da versão antiga.
3. Com as variáveis DB do ambiente carregadas, simule:
   `php database/migrations/run_giftback_expiration_migration.php`.
4. Aplique: `php database/migrations/run_giftback_expiration_migration.php --apply --acknowledge-writes-paused`.
   A migração preserva valores/histórico e cria uma abertura sem vencimento por carteira,
   com marcador inclusive para saldo zero. Reexecutá-la não duplica o saldo de abertura.
5. Só libere o tráfego se a reconciliação concluir (`ready: true`). Desative a pausa,
   reinicie os processos PHP e habilite o processador de vencimentos. As lojas começam
   sem vencimento; configure-as individualmente conforme necessário.

Não reimplante o motor antigo após começar a registrar vencimentos: seus recálculos por
compras ignoram consumo e expiração. Em incidentes, mantenha os escritores em manutenção
e corrija a versão preservando os eventos; não apague as tabelas do histórico.

## Processamento e operação

`php scripts/cron/expire-giftback.php --dry-run` simula candidatos.
`php scripts/cron/expire-giftback.php` processa até 100 carteiras por execução.
O endpoint `/api/internal/giftback-expiration?limit=100` requer
`Authorization: Bearer <CRON_SECRET>` e não depende de WhatsApp ou e-mail.

No VPS, prepare `/etc/klubecash-worker.env` com `KLUBECASH_SITE_URL` e `CRON_SECRET`.
Instale `scripts/vps/klubecash-giftback-worker.service` no systemd, ajustando `ExecStart`
para o caminho real do checkout. Ele chama o worker independente a cada minuto e registra
as respostas no journal. Alternativamente, agende o CLI a cada minuto com as variáveis DB
carregadas. Use somente um desses agendamentos; chamadas repetidas continuam idempotentes.
Em outros hosts, agende o endpoint na frequência disponível. Consultar ou gastar saldo
também verifica vencimentos, portanto atraso do agendamento não permite gastar créditos vencidos.

Na publicação de 30/09/2026, o projeto Vercel utiliza o plano Hobby. O `vercel.json`
agenda o processador diariamente (`5 3 * * *`, horário UTC); o plano não permite
execução a cada minuto. Para essa frequência, instale o worker externo descrito acima.
O processamento síncrono continua impedindo uso de créditos vencidos entre execuções.

Monitore `giftback.expiration`, `processed`, `expiredCents`, falhas HTTP 503 e carteiras
pendentes. Carteiras divergentes geram erro de reconciliação, nunca recomposição pela soma
das vendas. Registros financeiros, lotes, alocações, auditoria e respostas idempotentes
são gravados juntos. Uma falha desfaz a operação.
O lote continua nas demais carteiras quando uma falha: `failed` e `failedWallets`
identificam as pendências, o endpoint responde 503 e o CLI termina com código 1.
Após corrigir a causa, repetir o processamento não duplica expirações já concluídas.

## Avisos de vencimento pelo WhatsApp

O worker de notificações (`/api/internal/notifications`) avisa o cliente antes de um crédito vencer.
Os avisos só leem `cashback_creditos`; nunca alteram saldo. Ficam **desligados** até
`GIFTBACK_NOTICES_ENABLED=true` (no Coolify: `KLUBCASH_GIFTBACK_NOTICES_ENABLED`). Eles dependem do
worker de notificações (`KLUBCASH_VPS_WORKERS_ENABLED=true` e WAHA conectado).

Marcos: 20, 14, 7 e 1 dia antes do último dia de validade, mas só os que cabem até a metade da
validade original (`policy_days`). 40 dias: 20/14/7/1; 28–39: 14/7/1; 14–27: 7/1; 2–13: 1; sem
validade ou 1 dia: nenhum. Créditos sem vencimento (inclusive o saldo de abertura) não recebem aviso.

- A agenda fica em `cashback_credito_avisos` (uma linha por crédito, marco e validade). A chave
  única impede duplicidade; rodar o worker de novo nunca reenvia.
- Os avisos saem entre 09h e 20h (Brasília), no máximo 30 clientes por rodada, com **uma mensagem
  por cliente** reunindo todas as lojas com crédito a vencer.
- Se o worker ficou parado, só o marco mais próximo é enviado; os mais distantes são descartados.
  O texto sempre usa os dias realmente restantes.
- Antes de enviar, o crédito é conferido: sem saldo, vencido ou prorrogado/reativado, o aviso é
  descartado (`credit_changed`) e uma agenda nova nasce para a nova validade.
- Telefone vazio ou inválido: `skipped`. Falha temporária: até 5 tentativas com espera. Resultado
  incerto (`delivery_unknown`) nunca é reenviado.
- Não há opt-out por enquanto. Cada cliente recebe no máximo 4 avisos por crédito e uma mensagem por dia.

Ativação: com backup feito, `php database/migrations/run_giftback_notice_migration.php` (simula) e
`... --apply` (cria só a tabela). Depois defina `KLUBCASH_GIFTBACK_NOTICES_ENABLED=true` e reimplante.
Antes do primeiro envio real, confira o JSON do worker: `expiryNotices.candidates` mostra quantos
créditos entrariam. Acompanhe `expiryNotices.sent/skipped/failed` e a tabela `cashback_credito_avisos`.

## Testes isolados

O teste financeiro **não carrega `.env` nem o bootstrap**. Exige explicitamente um servidor
MySQL/MariaDB descartável em loopback e cria um banco vazio `giftback_test_<id>` por execução:

```powershell
$env:GIFTBACK_TEST_DSN='mysql:host=127.0.0.1;port=33367'
$env:GIFTBACK_TEST_USER='root'
php tests/php/giftback_ledger_integration.php
php tests/php/giftback_client_unit.php
```

Configure `GIFTBACK_TEST_PASSWORD` se necessário. Nunca aponte a instância de testes para
dados reais. Os bancos de fixtures permanecem no servidor descartável para diagnóstico.
O teste cobre serviços reais de venda/admin, migração, FEFO, datas, isolamento por cliente
e loja, ciclos de reativação, estornos legados, falhas atômicas e conexões concorrentes.

Também executar `npm test` na raiz e, em `KlubeCashNew`, `npm test`, `npm run lint` e
`npm run typecheck`. Os antigos testes de integração da aplicação usam a conexão normal
e não devem ser usados contra produção para validar esta funcionalidade.
