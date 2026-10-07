# Consulta de saldo por link da loja

O endereço público é `/saldo/{token}`. A página apresenta apenas o nome da loja antes da autenticação. O saldo é obtido da carteira de giftback individual da loja, com vencimentos liquidados no momento da leitura. Nenhuma imagem de QR code é gerada.

## Ativação

1. Em um ambiente com a migração de giftback já ativa, execute `php database/migrations/run_store_wallet_link_migration.php` para simular. O comando não modifica o banco sem `--apply`.
2. Execute `php database/migrations/run_store_wallet_link_migration.php --apply`. Ele confere a reconciliação das carteiras antes e depois e cria um link aleatório para cada loja aprovada. A execução é reentrante; links existentes não mudam.
3. Publique backend e frontend compatíveis no mesmo rollout. Confira `Admin → Links das lojas`, o link no perfil do lojista e um acesso com conta fictícia. A configuração exige `JWT_SECRET` forte e as variáveis WAHA existentes. Sem envio confirmado pelo WhatsApp, o código não autoriza consulta.

Um link desativado, trocado ou de loja não aprovada retorna 404. A troca exige versão atual e registra auditoria; o link antigo deixa de funcionar imediatamente. Placas já impressas devem ser substituídas.

## Segurança e vinculação

- Visitantes confirmam telefone por código de 6 dígitos válido por 5 minutos, até 5 tentativas e reenvio após 60 segundos. A sessão temporária limita-se a uma loja por 30 minutos.
- Login da página aceita somente clientes ativos; cadastro exige telefone confirmado, e-mail válido e duas senhas iguais. O visitante é convertido no mesmo usuário.
- A vinculação de conta existente move somente os ativos financeiros da loja indicada, dentro de transação com bloqueio das carteiras. Créditos, alocações, transações, histórico e estornos preservam seus IDs. Colisões de origem ou cadastros ambíguos são recusados para revisão administrativa. `store_wallet_claims` registra valor transferido, antes/depois, quantidade de registros e desafio confirmado.
- Repetição da vinculação não soma novamente. Uso e estorno de transações antigas permanecem idempotentes após a troca de titularidade.
- Não há cache das respostas de saldo; token, nome ou telefone isolados não revelam carteira. Motivos administrativos do giftback não são expostos.

## Testes isolados

`tests/php/store_wallet_link_integration.php` exige `WALLET_LINK_TEST_DSN=mysql:host=127.0.0.1;port=<porta>` em um servidor MySQL/MariaDB descartável, sem carregar `.env`. Ele cria e remove um banco com dados fictícios. Execute também a suíte Next (`npm test`, `npm run lint`, `npm run build` em `KlubeCashNew`). Não execute esses testes financeiros contra o banco de produção.
