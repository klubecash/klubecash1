# Inventário automático de rotas

> Arquivo gerado por `npm run docs:routes`. Não editar manualmente.

| Métodos | Caminho | Nome | Handler | Middlewares |
|---|---|---|---|---|
| `GET, POST` | `/` | `home` | `index.php` | `nenhum` |
| `GET, POST` | `/admin/ajax/auth` | `admin.ajax.auth` | `controllers/AuthController.php` | `admin` |
| `GET, POST` | `/admin/ajax/store-balance-payments` | `admin.ajax.store-balance-payments` | `controllers/StoreBalancePaymentController.php` | `admin` |
| `GET, POST` | `/admin/ajax/store-controller` | `admin.ajax.store-controller` | `controllers/StoreController.php` | `admin` |
| `GET, POST` | `/admin/ajax/stores` | `admin.ajax.stores` | `controllers/AdminController.php` | `admin` |
| `GET, POST` | `/admin/ajax/stores-direct` | `admin.ajax.stores-direct` | `controllers/AjaxStoreController.php` | `admin` |
| `GET, POST` | `/admin/ajax/transaction-controller` | `admin.ajax.transaction-controller` | `controllers/TransactionController.php` | `admin` |
| `GET, POST` | `/admin/ajax/transactions` | `admin.ajax.transactions` | `controllers/AdminController.php` | `admin` |
| `GET, POST` | `/admin/ajax/users` | `admin.ajax.users` | `controllers/AdminController.php` | `admin` |
| `GET, POST` | `/admin/assinaturas` | `admin.subscriptions` | `views/admin/subscriptions.php` | `admin` |
| `GET, POST` | `/admin/cashback-config` | `admin.cashback` | `views/admin/cashback-config.php` | `admin` |
| `GET, POST` | `/admin/comissoes` | `admin.commissions` | `views/admin/commissions.php` | `admin` |
| `GET, POST` | `/admin/configuracoes` | `admin.settings` | `views/admin/settings.php` | `admin` |
| `GET, POST` | `/admin/dashboard` | `admin.dashboard` | `views/admin/dashboard.php` | `admin` |
| `GET, POST` | `/admin/email-marketing` | `admin.email-marketing` | `views/admin/email-marketing.php` | `admin` |
| `GET, POST` | `/admin/email-templates` | `admin.email-templates` | `views/admin/email-templates.php` | `admin` |
| `GET, POST` | `/admin/lojas` | `admin.stores` | `views/admin/stores.php` | `admin` |
| `GET, POST` | `/admin/pagamentos` | `admin.payments` | `views/admin/payments.php` | `admin` |
| `GET, POST` | `/admin/relatorios` | `admin.reports` | `views/admin/reports.php` | `admin` |
| `GET, POST` | `/admin/saldo` | `admin.balance` | `views/admin/balance.php` | `admin` |
| `GET, POST` | `/admin/store-subscription` | `admin.store-subscription` | `views/admin/store-subscription.php` | `admin` |
| `GET, POST` | `/admin/transacao/{id:\d+}` | `admin.transaction` | `views/admin/transaction-details.php` | `admin` |
| `GET, POST` | `/admin/transacoes` | `admin.transactions` | `views/admin/purchases.php` | `admin` |
| `GET, POST` | `/admin/usuarios` | `admin.users` | `views/admin/users.php` | `admin` |
| `GET, POST, OPTIONS` | `/api/abacatepay` | `api.abacatepay` | `api/abacatepay.php` | `api, store` |
| `POST` | `/api/abacatepay-webhook` | `api.abacatepay.webhook` | `api/abacatepay-webhook.php` | `api` |
| `GET, POST, OPTIONS` | `/api/balance` | `api.balance` | `api/balance.php` | `api, auth` |
| `GET, POST, PUT, DELETE, OPTIONS` | `/api/commissions` | `api.commissions` | `api/commissions.php` | `api, admin` |
| `GET, POST, PUT, DELETE, OPTIONS` | `/api/employees` | `api.employees` | `api/employees.php` | `api, store` |
| `GET, POST, OPTIONS` | `/api/funcionarios` | `api.funcionarios` | `api/funcionarios.php` | `api, store` |
| `GET` | `/api/get-store-id` | `api.store-id` | `api/get-store-id.php` | `api, auth` |
| `GET` | `/api/health` | `api.health` | `api/health.php` | `api` |
| `GET` | `/api/homepage-context` | `api.homepage-context` | `api/homepage-context.php` | `api` |
| `GET, POST, OPTIONS` | `/api/mercadopago` | `api.mercadopago` | `api/mercadopago.php` | `api, store` |
| `POST` | `/api/mercadopago-webhook` | `api.mercadopago.webhook` | `api/mercadopago-webhook.php` | `api` |
| `POST, OPTIONS` | `/api/openpix` | `api.openpix` | `api/openpix.php` | `api, store` |
| `GET` | `/api/payment-receipt` | `api.payment-receipt` | `api/payment-receipt.php` | `api, auth` |
| `POST, OPTIONS` | `/api/payments` | `api.payments` | `api/payments.php` | `api, store` |
| `GET` | `/api/search-stores` | `api.search-stores` | `api/search-stores.php` | `api, auth` |
| `GET, POST, OPTIONS` | `/api/store-client-search` | `api.store-client-search` | `api/store-client-search.php` | `api, store` |
| `POST` | `/api/store-details` | `api.store-details` | `api/store-details.php` | `api, auth, store` |
| `POST, OPTIONS` | `/api/store-payment` | `api.store-payment` | `api/store-payment.php` | `api, store` |
| `POST` | `/api/store-transactions` | `api.store-transactions` | `api/store-transactions.php` | `api, auth, store` |
| `GET` | `/api/store_details` | `api.store-details.legacy` | `api/store_details.php` | `api` |
| `GET, POST, PUT, DELETE, OPTIONS` | `/api/stores` | `api.stores` | `api/stores.php` | `api, admin` |
| `GET, POST, OPTIONS` | `/api/stripe` | `api.stripe` | `api/stripe.php` | `api, store` |
| `POST` | `/api/stripe-webhook` | `api.stripe.webhook` | `api/stripe-webhook.php` | `api` |
| `GET, POST, PUT, DELETE, OPTIONS` | `/api/transactions` | `api.transactions` | `api/transactions.php` | `api, auth` |
| `GET, POST, PUT, DELETE, OPTIONS` | `/api/users` | `api.users` | `api/users.php` | `api, admin` |
| `GET, POST, PUT, DELETE, OPTIONS` | `/api/users/{id:\d+}` | `api.users.show` | `api/users.php` | `api, admin` |
| `GET` | `/blog` | `blog.index` | `views/blog/index.php` | `nenhum` |
| `GET` | `/blog/{slug:[a-zA-Z0-9-]+}` | `blog.post` | `views/blog/post.php` | `nenhum` |
| `GET, POST` | `/cadastro-loja` | `store.register` | `views/stores/register.php` | `nenhum` |
| `GET, POST` | `/cashback-brasil` | `marketing.cashback-brasil` | `index.php` | `nenhum` |
| `GET` | `/categoria/{slug:[a-zA-Z0-9-]+}` | `blog.category` | `views/blog/categoria.php` | `nenhum` |
| `GET, POST` | `/cliente/actions` | `client.actions` | `controllers/client_actions.php` | `client` |
| `GET, POST` | `/cliente/ajax/controller` | `client.ajax.controller` | `controllers/ClientController.php` | `client` |
| `GET, POST` | `/cliente/dashboard` | `client.dashboard` | `views/client/dashboard.php` | `client` |
| `GET, POST` | `/cliente/extrato` | `client.statement` | `views/client/statement.php` | `client` |
| `GET, POST` | `/cliente/lojas-parceiras` | `client.stores` | `views/client/partner-stores.php` | `client` |
| `GET, POST` | `/cliente/perfil` | `client.profile` | `views/client/profile.php` | `client` |
| `GET, POST` | `/cliente/saldo` | `client.balance` | `views/client/balance.php` | `client` |
| `GET, POST` | `/como-funciona` | `marketing.how` | `views/marketing/como-funciona.php` | `nenhum` |
| `GET, POST` | `/home` | `home.legacy` | `index.php` | `nenhum` |
| `GET, POST` | `/login` | `auth.login` | `views/auth/login.php` | `guest` |
| `GET, POST` | `/logout` | `auth.logout` | `views/auth/logout.php` | `auth` |
| `GET, POST` | `/loja/autogestao` | `client.self-service` | `views/client/partner-stores.php` | `client` |
| `GET, POST` | `/loja/hub` | `client.hub` | `views/client/partner-stores.php` | `client` |
| `GET, POST` | `/lojas/cadastro` | `store.register.canonical` | `views/stores/register.php` | `nenhum` |
| `GET, POST` | `/lojas/detalhes/{id:\d+}` | `store.details` | `views/stores/details.php` | `nenhum` |
| `GET, POST` | `/parceria-comercial` | `store.commercial-partner` | `views/stores/register.php` | `nenhum` |
| `GET` | `/politica-de-privacidade` | `privacy` | `politica-de-privacidade.php` | `nenhum` |
| `GET, POST` | `/programa-fidelidade` | `marketing.fidelidade` | `index.php` | `nenhum` |
| `GET, POST` | `/recuperar-senha` | `auth.recover` | `views/auth/recover-password.php` | `guest` |
| `GET, POST` | `/registro` | `auth.register` | `views/auth/register.php` | `guest` |
| `GET` | `/robots.txt` | `robots` | `robots.php` | `nenhum` |
| `GET, POST` | `/seja-parceiro` | `store.partner` | `views/stores/register.php` | `nenhum` |
| `GET, POST` | `/sistema-cashback` | `marketing.cashback` | `index.php` | `nenhum` |
| `GET` | `/sitemap.xml` | `sitemap` | `sitemap.php` | `nenhum` |
| `GET, POST` | `/store` | `store.home` | `views/stores/dashboard.php` | `store` |
| `GET, POST` | `/store/dashboard` | `store.dashboard` | `views/stores/dashboard.php` | `store` |
| `GET, POST` | `/store/fatura-pix` | `store.invoice-pix` | `views/stores/invoice-pix.php` | `store` |
| `GET, POST` | `/store/funcionarios` | `store.employees` | `views/stores/employees.php` | `store` |
| `GET, POST` | `/store/historico-pagamentos` | `store.payment-history` | `views/stores/payment-history.php` | `store` |
| `GET, POST` | `/store/meu-plano` | `store.subscription` | `views/stores/subscription.php` | `store` |
| `GET, POST` | `/store/pagamento` | `store.payment` | `views/stores/payment.php` | `store` |
| `GET, POST` | `/store/pagamento-pix` | `store.payment-pix` | `views/stores/payment-pix.php` | `store` |
| `GET, POST` | `/store/perfil` | `store.profile` | `views/stores/profile.php` | `store` |
| `GET, POST` | `/store/registrar-transacao` | `store.transaction.create` | `views/stores/register-transaction.php` | `store` |
| `GET, POST` | `/store/transacoes` | `store.transactions` | `views/stores/transactions.php` | `store` |
| `GET, POST` | `/store/transacoes-pendentes` | `store.pending` | `views/stores/pending-commissions.php` | `store` |
| `GET, POST` | `/store/upload-lote` | `store.batch` | `views/stores/batch-upload.php` | `store` |
| `GET, POST` | `/vantagens-cashback` | `marketing.benefits` | `views/marketing/vantagens.php` | `nenhum` |

Total: **93 rotas declaradas**.
