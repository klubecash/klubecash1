# KLUBCASHVPS: implantação paralela

Esta pilha mantém `www.klubecash.com` e os processadores na Vercel. O Coolify deve criar uma aplicação Docker Compose no projeto `KLUBCASHVPS`, a partir da branch `codex/klubcashvps` e do arquivo `deploy/compose.coolify.yml`. Somente o serviço `web` recebe o domínio `https://vps.klubecash.com:3000`; não publicar portas para `legacy-http`, `php`, MySQL ou WAHA.

O workflow `coolify-images.yml` publica três imagens no GHCR, todas marcadas pelo SHA do commit. Após os três builds concluírem, configurar `KLUBCASH_IMAGE_TAG` com o SHA exato. Se o pacote GHCR for privado, configurar acesso de leitura do Coolify antes do deploy; não colocar token no Compose nem tornar o banco público.

Preencher no Coolify as variáveis `KLUBCASH_*` exigidas pelo Compose como segredos de execução. Nunca inserir valores no Git. A pilha usa `SESSION_DRIVER=database`, cookie seguro, banco TLS na rede interna, `WAHA_MANAGE_WEBHOOK=false`, `VPS_WORKERS_ENABLED=false` e `BILLING_WRITES_ENABLED=false`. Assim, nenhum worker da VPS assume o webhook, a fila ou cobranças durante a validação. Um `CRON_SECRET` da VPS, se configurado, deve ser diferente do da Vercel.

O volume `uploads-data` é persistente e precisa receber os uploads já referenciados pelo banco antes da validação visual. Um volume novo vazio não recupera arquivos que existam somente em outro servidor. `logs-data` preserva logs legados. Fazer inventário dos arquivos referenciados e copiar apenas os necessários, com conferência de quantidade e hashes.

Não aplicar migrações no MySQL atual até obter backup recuperável fora da VPS, ensaiar restauração e migrações em banco isolado, e reconciliar as carteiras. A ausência de backups agendados no Coolify não comprova que haja outro backup. Após o ensaio, aplicar somente objetos aditivos comprovadamente ausentes; registrar o esquema e os saldos antes/depois.

Validar HTTPS, `/api/health`, assets, sessões dos três perfis, CSRF, carteira, equipe, vendas, SMTP com destinatário de teste, WAHA sem registrar webhook, pagamentos somente em sandbox, uploads, logs, consumo de CPU/RAM/disco e reinícios. Não apontar `www` ou habilitar workers até uma virada separada e coordenada.
