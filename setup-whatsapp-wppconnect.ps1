param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,

    [Parameter(Mandatory = $true)]
    [string]$Session,

    [Parameter(Mandatory = $true)]
    [string]$Token,

    [switch]$Disable,

    [string]$TemplateLanguage = 'pt_BR',

    [int]$Timeout = 20,

    [int]$AckTimeout = 10,

    [string]$MediaDir = 'uploads/whatsapp',

    [string]$LogPath = 'logs/whatsapp.log'
)

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
$configPath = Join-Path $projectRoot 'config/whatsapp.php'
$mediaFullPath = Join-Path $projectRoot $MediaDir
$logFullPath = Join-Path $projectRoot $LogPath

if (-not (Test-Path $projectRoot)) {
    throw "Não foi possível determinar o diretório do projeto."
}

$mediaParent = Split-Path $mediaFullPath -Parent
if (-not (Test-Path $mediaParent)) {
    New-Item -ItemType Directory -Path $mediaParent -Force | Out-Null
}

if (-not (Test-Path $mediaFullPath)) {
    New-Item -ItemType Directory -Path $mediaFullPath -Force | Out-Null
}

$logParent = Split-Path $logFullPath -Parent
if (-not (Test-Path $logParent)) {
    New-Item -ItemType Directory -Path $logParent -Force | Out-Null
}

if (-not (Test-Path $logFullPath)) {
    New-Item -ItemType File -Path $logFullPath -Force | Out-Null
}

$enabledLiteral = $Disable.IsPresent ? 'false' : 'true'
$baseUrlLiteral = $BaseUrl.TrimEnd('/')
$sessionLiteral = $Session
$tokenLiteral = $Token
$templateLanguageLiteral = $TemplateLanguage
$timeoutLiteral = [Math]::Max(5, $Timeout)
$ackLiteral = [Math]::Max(5, $AckTimeout)
$mediaLiteral = $MediaDir.Replace('\\', '/')
$logLiteral = $LogPath.Replace('\\', '/')

function Escape-PhpString([string]$value) {
    return $value -replace "'", "\\'"
}

$configContent = @"
<?php
// Gerado automaticamente por setup-whatsapp-wppconnect.ps1 em $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
define('WHATSAPP_ENABLED', $enabledLiteral);
define('WHATSAPP_BASE_URL', '$(Escape-PhpString $baseUrlLiteral)');
define('WHATSAPP_SESSION_NAME', '$(Escape-PhpString $sessionLiteral)');
define('WHATSAPP_API_TOKEN', '$(Escape-PhpString $tokenLiteral)');
define('WHATSAPP_TEMPLATE_LANGUAGE', '$(Escape-PhpString $templateLanguageLiteral)');
define('WHATSAPP_HTTP_TIMEOUT', $timeoutLiteral);
define('WHATSAPP_CONNECT_RETRIES', 5);
define('WHATSAPP_ACK_TIMEOUT', $ackLiteral);
define('WHATSAPP_MEDIA_DIR', dirname(__DIR__) . '/$(Escape-PhpString $mediaLiteral)');
define('WHATSAPP_LOG_PATH', dirname(__DIR__) . '/$(Escape-PhpString $logLiteral)');
define('WHATSAPP_DEFAULT_FALLBACK_MESSAGE', 'Não foi possível completar o envio pelo WhatsApp. Tente novamente mais tarde.');
?>
"@

Set-Content -Path $configPath -Value $configContent -Encoding UTF8

Write-Host "Arquivo de configuração atualizado em $configPath" -ForegroundColor Green
Write-Host "Diretório de mídia: $mediaFullPath" -ForegroundColor Yellow
Write-Host "Log de integração: $logFullPath" -ForegroundColor Yellow
Write-Host "Execute: php scripts/demo_whatsapp_tx.php <telefone>" -ForegroundColor Cyan