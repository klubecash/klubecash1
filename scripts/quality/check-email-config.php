<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('SMTP_HOST=smtp.resend.com');
putenv('SMTP_PORT=465');
putenv('SMTP_USERNAME=resend');
putenv('SMTP_PASSWORD=quality-check-placeholder');
putenv('SMTP_FROM_EMAIL=notificacoes@klubecash.com');
putenv('SMTP_FROM_NAME=Klube Cash');
putenv('SMTP_ENCRYPTION=smtps');

require_once dirname(__DIR__, 2) . '/utils/Email.php';

$emailReflection = new ReflectionClass(Email::class);
$initialize = $emailReflection->getMethod('init');
$configureMailer = $emailReflection->getMethod('configureMailer');
$fromEmail = $emailReflection->getProperty('fromEmail');

$initialize->setAccessible(true);
$configureMailer->setAccessible(true);
$fromEmail->setAccessible(true);

if ($initialize->invoke(null) !== true) {
    fwrite(STDERR, "FALHA: a configuração SMTP de teste não foi inicializada.\n");
    exit(1);
}

$mailer = new PHPMailer\PHPMailer\PHPMailer(true);
$configureMailer->invoke(null, $mailer);
$replyTo = $mailer->getReplyToAddresses();

$checks = [
    'host SMTP do Resend' => $mailer->Host === 'smtp.resend.com',
    'porta SMTPS' => $mailer->Port === 465,
    'usuário autenticado' => $mailer->Username === 'resend',
    'remetente configurado' => $fromEmail->getValue() === 'notificacoes@klubecash.com'
        && $mailer->From === 'notificacoes@klubecash.com'
        && $mailer->Sender === 'notificacoes@klubecash.com',
    'reply-to configurado' => isset($replyTo['notificacoes@klubecash.com']),
    'criptografia SMTPS' => $mailer->SMTPSecure === PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
    'certificado sem exceções inseguras' => $mailer->SMTPOptions === [],
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? 'OK' : 'FALHA') . ': ' . $description . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
