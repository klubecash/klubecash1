$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dbPassword = [Environment]::GetEnvironmentVariable('DB_PASS', 'Process')

if ([string]::IsNullOrWhiteSpace($dbPassword)) {
    $dbPassword = [Environment]::GetEnvironmentVariable('DB_PASS', 'User')
}

if ([string]::IsNullOrWhiteSpace($dbPassword)) {
    throw 'Variável de ambiente DB_PASS não configurada.'
}

$env:DB_PASS = $dbPassword
$env:SITE_URL = 'http://127.0.0.1:8000'
$env:SMTP_HOST = 'smtp.resend.com'
$env:SMTP_PORT = '465'
$env:SMTP_USERNAME = 'resend'
$env:SMTP_FROM_EMAIL = 'notificacoes@klubecash.com'
$env:SMTP_FROM_NAME = 'Klube Cash'
$env:SMTP_ENCRYPTION = 'smtps'

$smtpPassword = [Environment]::GetEnvironmentVariable('SMTP_PASSWORD', 'Process')
if ([string]::IsNullOrWhiteSpace($smtpPassword)) {
    $smtpPassword = [Environment]::GetEnvironmentVariable('SMTP_PASSWORD', 'User')
}
if (-not [string]::IsNullOrWhiteSpace($smtpPassword)) {
    $env:SMTP_PASSWORD = $smtpPassword
}

php `
    -d extension=openssl `
    -d extension=pdo_mysql `
    -d extension=fileinfo `
    -d upload_max_filesize=4M `
    -d post_max_size=5M `
    -S 127.0.0.1:8000 `
    -t $root `
    (Join-Path $root 'router.php')
