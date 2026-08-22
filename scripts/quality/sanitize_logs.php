<?php

declare(strict_types=1);

/** Remove dados pessoais de logs locais antigos sem apagar timestamps ou request IDs. */

$root = dirname(__DIR__, 2);
$files = glob($root . '/logs/*.log') ?: [];
$changed = 0;

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false || $contents === '') {
        continue;
    }

    $sanitized = preg_replace_callback('/^([^\r\n]*\])\s+.*LOGIN.*$/mi', static function (array $match): string {
        return $match[1] . ' auth.login.legacy_event redacted=true';
    }, $contents) ?? $contents;
    $sanitized = preg_replace('/^([^\r\n]*\])\s+Sess(?:ão|Ã£o)[^\r\n]*$/mi', '$1 auth.login.session_started redacted=true', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/Session data:\s*[^\r\n]*/i', 'session_data=[redacted]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/("(?:email|phone|cellphone|taxId|user_name|user_email|name)"\s*:\s*)"[^"]*"/i', '$1"[redacted]"', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/("user_id"\s*:\s*)(?:\d+|"[^"]*")/i', '$1"[redacted]"', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/(Nome:\s*)[^\r\n,}]+/iu', '$1[redacted-name]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\b(?:\+?55\s*)?(?:\(?\d{2}\)?[\s.-]*)?\d{4,5}[\s.-]*\d{4}(?:@s\.whatsapp\.net)?\b/', '[redacted-phone]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\b[a-f0-9]{32}\b/i', '[redacted-session]', $sanitized) ?? $sanitized;

    if ($sanitized !== $contents) {
        file_put_contents($file, $sanitized, LOCK_EX);
        $changed++;
    }
}

echo json_encode(['status' => 'success', 'filesChanged' => $changed], JSON_UNESCAPED_SLASHES) . PHP_EOL;
