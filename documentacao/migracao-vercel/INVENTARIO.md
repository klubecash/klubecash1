# Inventário inicial

## Aplicação

| Área | Situação inicial | Direção |
|---|---|---|
| Site público | Ativo | Preservar |
| Login e cadastro | Ativo com sessões locais/duplicadas | Centralizar |
| Recuperação de senha | Presente | Preservar SMTP |
| Clientes e carteiras | Presente | Preservar |
| Lojas e funcionários | Presente | Preservar |
| Cashback, saldos e extratos | Presente | Preservar e testar |
| Administração básica | Parcial, com imports quebrados | Corrigir |
| Financeiro/assinaturas | Incompleto | Ocultar para reconstrução futura |
| Blog | Quebrado por ausência de `BlogPost` | Desativar ou fallback |
| Uploads | Disco local efêmero | Migrar para Blob |
| Logs | Parte em arquivos locais | Migrar para log estruturado |

## Telas administrativas inexistentes

- `views/admin/audit-log.php`
- `views/admin/user-logs.php`
- `views/admin/plans.php`
- `views/admin/employee-logs.php`
- `views/admin/email-campaigns.php`

O `.htaccess` contém duas referências a `audit-log.php`; por isso o levantamento original contabilizou seis rotas quebradas, mas cinco arquivos únicos.

## Páginas existentes com falhas observadas

- `users.php` e `settings.php`: uso de `AdminController` sem carregamento consistente.
- `email-marketing.php`: usa constantes antes do bootstrap.
- `email-templates.php`: caminho relativo incorreto para o banco.
- `commissions.php`: proteção de acesso inconsistente.
- Blog: exige `models/BlogPost.php`, que não existe.

## Sessões

Foram encontradas inicializações de sessão em configurações, controllers, APIs, views e componentes. A tabela `sessoes` atual registra autenticação, mas não armazena o payload nativo do PHP; será mantida separada de `php_sessions`.

## Escritas locais incompatíveis com serverless

- Logos de lojas.
- Comprovantes e comprovantes de saldo.
- Mídias do WhatsApp.
- Logs de API, pagamentos, webhooks, cron e auditoria.
- Exportações e arquivos JSON de transações.

## Integrações

Remover código ativo e interface de Mercado Pago, OpenPix, AbacatePay, Stripe, Google OAuth e WhatsApp. Manter registros históricos no banco sem executar chamadas externas. Manter apenas SMTP e consulta de CEP.

## Matriz de acesso alvo

| Área | Visitante | Cliente | Loja | Funcionário | Admin |
|---|---:|---:|---:|---:|---:|
| Site público | Sim | Sim | Sim | Sim | Sim |
| Perfil próprio | Não | Sim | Sim | Sim | Sim |
| Carteiras/saldo/extrato | Não | Sim | Não | Não | Sim |
| Registro de cashback | Não | Não | Sim | Sim autorizado | Sim |
| Gestão de funcionários | Não | Não | Sim | Conforme permissão | Sim |
| Administração | Não | Não | Não | Não | Sim |
| Financeiro antigo | Não | Não | Não | Não | Não |

## Tabelas do núcleo

`usuarios`, `usuarios_contato`, `usuarios_endereco`, `lojas`, `lojas_contato`, `lojas_endereco`, `cashback_saldos`, `cashback_movimentacoes`, `transacoes_cashback`, `transacoes_saldo_usado`, `transacoes_status_historico`, `favorites`, `lojas_favoritas`, `recuperacao_senha`, `login_attempts`, `sessoes`.

## Tabelas históricas/futuro financeiro

`assinaturas`, `planos`, `faturas`, `pagamentos_comissao`, `pagamentos_devolucoes`, `pagamento_transacoes`, `pagamentos_transacoes`, `store_balance_payments`, `webhook_events`, `webhook_errors`, tabelas de comissões e reservas administrativas. Não apagar durante esta migração.
