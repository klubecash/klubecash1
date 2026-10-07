# Redes, filiais e vendedores

Cada filial continua sendo uma loja independente. A rede apenas autoriza o uso
compartilhado de créditos válidos; crédito, validade, venda, vendedor e estorno
permanecem associados às suas origens. Nenhuma rede é criada automaticamente.

## Preparação e migração

1. Instalar primeiro as migrações de giftback individual e link da loja.
2. Suspender temporariamente as gravações financeiras e executar
   `php database/migrations/run_store_network_migration.php` sem `--apply`.
   O resultado mostra a estrutura pendente, quantidades de lojas/funcionários,
   vendas históricas sem vendedor comprovado e divergências das carteiras.
3. Exigir zero divergências e zero créditos sem carteira. Conferir a soma dos
   `remaining_cents` com `cashback_saldos.saldo_disponivel` em centavos.
4. Ensaiar o `--apply` em uma cópia isolada com dados fictícios, reexecutar
   para confirmar idempotência e testar venda, expiração e estorno entre filiais.
5. Aplicar `php database/migrations/run_store_network_migration.php --apply`
   somente na janela de ativação controlada. A migração não define vendedor
   para venda antiga por inferência de `criado_por`.
   Se a conciliação impedir a ativação e o código novo já estiver publicado,
   `--apply-schema-only` instala somente tabelas, colunas e vínculos antigos
   para restaurar o acesso, sem alterar saldos. Entrada e retomada de filiais
   na rede permanecem bloqueadas até zerar todas as divergências e créditos
   sem carteira; depois execute a simulação e `--apply` normalmente.
6. Liberar as gravações; habilitar uma rede e suas filiais gradualmente em
   Admin → Redes e filiais. Examinar a conciliação antes de suspender/separar.

O teste financeiro `tests/php/store_network_integration.php` só aceita
`GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<porta>` de um MySQL descartável.
Ele cria e remove seu próprio banco temporário. Nunca apontá-lo para o banco da
aplicação.

## Regras operacionais

- Cada conta de funcionário pode ter vínculos ativos ou convites pendentes por
  filial. Um convite para uma conta existente precisa ser aceito por ela.
- Se houver mais de uma filial disponível, a conta escolhe a filial ativa. O
  servidor revalida esse vínculo a cada requisição.
- `criado_por` identifica quem registrou a venda; `vendedor_id` identifica quem
  vendeu. No CSV, `email_vendedor` é opcional por linha. Em ausência de prova,
  a venda fica como “Vendedor não identificado”. Só o admin pode corrigir,
  informando motivo/evidência; a alteração é auditada.
- Na rede, o uso de giftback segue a validade mais próxima entre todas as
  filiais ativas e registra a origem de cada parcela. Suspender uma filial
  impede novos usos cruzados, sem reescrever históricos ou estornos.
- A consulta e o uso do saldo da rede de um visitante exigem confirmação de
  telefone por WhatsApp. Vínculos de visitantes ficam limitados à rede
  confirmada; ambiguidades interrompem a operação para revisão.
- O importador CSV antigo e a API genérica antiga de criação de transações
  são recusados, pois não preservam a atribuição exigida. Usar as rotas v2.

## Verificação de entrega

Executar `php -l` nos PHP alterados; em `KlubeCashNew`, executar
`npm test -- --run`, `npm run typecheck`, `npm run lint` e `npm run build`. Validar os fluxos
no navegador em celular e desktop após aplicar a migração no ambiente isolado.

## Central de redes e conciliação

- O admin usa **Redes e filiais** para buscar lojas por nome/CNPJ e gestores por nome/e-mail. A central mostra filiais ativas/suspensas, histórico, uso cruzado e saúde das carteiras envolvidas.
- O gestor designado vê **Central da rede**, com uma conta de funcionário e seus vínculos separados por filial. Convites para contas existentes continuam pendentes até a aceitação. O titular e o gerente continuam limitados à equipe da filial onde têm permissão.
- O seletor de visão do dashboard, transações e relatórios é compartilhado. **Rede toda** e filtros nunca alteram a filial operacional: uma venda ou um CSV pertencem sempre à filial ativa indicada no menu.
- Execute `php database/migrations/run_network_central_migration.php` para simular e `--apply` somente após ensaio em MySQL isolado e janela de implantação. Essa migração cria apenas o histórico de reparos de carteira; pode ser repetida.
- O diagnóstico confere crédito individual, saldo agregado, marcador de migração e última movimentação das filiais participantes. O reparo pela interface só cria um agregado ausente quando todos os créditos são do tipo `grant`, a soma interna é válida e a última movimentação confirma exatamente o saldo. Exige evidência e registra antes/depois com administrador na mesma transação. Crédito legado, valor divergente e marcador ausente exigem revisão manual; nunca ajuste o saldo às cegas.
- O teste `tests/php/store_network_central_integration.php` só aceita `GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<porta>` apontando a um servidor descartável. Sem essa configuração, não execute esse teste contra o banco da aplicação.
