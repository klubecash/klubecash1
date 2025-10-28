# 🚀 INSTRUÇÕES DE DEPLOY - SISTEMA DE ASSINATURAS

## 📦 PASSO 1: UPLOAD VIA FTP

Faça upload destes 3 arquivos para o servidor:

```
LOCAL                                    →  SERVIDOR

controllers/SubscriptionController.php   →  /public_html/controllers/SubscriptionController.php
utils/StoreHelper.php                    →  /public_html/utils/StoreHelper.php
views/stores/subscription.php            →  /public_html/views/stores/subscription.php
```

**⚠️ IMPORTANTE**: Faça backup dos arquivos antigos antes de substituir!

---

## 💾 PASSO 2: EXECUTAR MIGRATIONS NO BANCO

### Opção A: Via phpMyAdmin (RECOMENDADO)

1. Acesse seu phpMyAdmin
2. Selecione o banco `klubecash`
3. Clique em "SQL"
4. **Primeiro**, cole e execute o conteúdo de:
   ```
   database/migrations/add_subscription_indexes.sql
   ```
5. **Depois**, cole e execute o conteúdo de:
   ```
   database/migrations/create_subscription_history_table.sql
   ```

### Opção B: Via Terminal SSH

Se tiver acesso SSH ao servidor:

```bash
cd /public_html/database/migrations
php run_indexes_migration.php
php run_history_table_migration.php
```

### Opção C: Via Linha de Comando MySQL

```bash
mysql -u root -p klubecash < add_subscription_indexes.sql
mysql -u root -p klubecash < create_subscription_history_table.sql
```

---

## ✅ PASSO 3: TESTAR FUNCIONALIDADES

### Teste 1: Proteção contra Duplicatas
1. Acesse como Admin: `/admin/store-subscription.php?loja_id=34`
2. Clique em "Gerar Fatura"
3. Tente gerar outra fatura no mesmo mês
4. ✅ **Esperado**: Mensagem de erro "Já existe fatura para este mês"

### Teste 2: Controle de Acesso sem Plano
1. No banco, altere status de assinatura para 'inadimplente':
   ```sql
   UPDATE assinaturas SET status = 'inadimplente' WHERE loja_id = 34;
   ```
2. Tente acessar Dashboard como lojista
3. ✅ **Esperado**: Redirecionamento para página "Meu Plano"

### Teste 3: Interface de Upgrade
1. Acesse como lojista: `/store/meu-plano`
2. Verifique se aparecem:
   - ✓ Plano atual com preço correto (/mês ou /ano)
   - ✓ Toggle mensal/anual (se plano suportar)
   - ✓ Planos superiores com botão "Fazer Upgrade"
3. ✅ **Esperado**: Interface completa e funcional

### Teste 4: Upgrade com Valor Proporcional
1. Faça upgrade de plano (ex: Básico → Profissional)
2. ✅ **Esperado**:
   - Cálculo automático de valor proporcional
   - Fatura gerada (se houver diferença)
   - Redirecionamento para pagamento PIX

---

## 🐛 TROUBLESHOOTING

### Erro: "Table 'klubecash.assinaturas_historico' doesn't exist"

**Solução**: Execute a migration `create_subscription_history_table.sql`

### Erro: "Duplicate key name 'idx_assinatura_status_created'"

**Solução**: Index já existe. Isso é normal, pode ignorar.

### Erro: "Call to undefined method FeatureGate::isActive()"

**Solução**: Verifique se o arquivo `utils/StoreHelper.php` foi atualizado corretamente

### Interface não mostra planos disponíveis

**Solução**: Verifique se existem planos cadastrados:
```sql
SELECT * FROM planos WHERE ativo = 1;
```

---

## 📋 CHECKLIST DE DEPLOY

- [ ] Backup dos 3 arquivos originais feito
- [ ] Upload dos 3 novos arquivos via FTP
- [ ] Migration de indexes executada
- [ ] Tabela de histórico criada
- [ ] Teste 1 (duplicatas) realizado
- [ ] Teste 2 (controle acesso) realizado
- [ ] Teste 3 (interface) realizado
- [ ] Teste 4 (upgrade) realizado
- [ ] Tudo funcionando ✅

---

## 📞 SUPORTE

Consulte a documentação completa em:
- `SISTEMA_ASSINATURAS_IMPLEMENTACAO.md`

Arquivos modificados têm comentários detalhados no código.

---

**Data de Deploy**: _____________
**Executado por**: _____________
**Status**: [ ] Concluído  [ ] Com Pendências
