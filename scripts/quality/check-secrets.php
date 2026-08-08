<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$secretNamePattern = '(?:PASS(?:WORD)?|SECRET|TOKEN|ACCESS_TOKEN|API_KEY|APP_ID|CLIENT_SECRET|PRIVATE_KEY)';
$findings = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (preg_match('#/(?:node_modules|vendor|\.git)/#', $path)) {
        continue;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $index => $line) {
        if (preg_match(
            "/define\\(\\s*['\"]([A-Z0-9_]*{$secretNamePattern}[A-Z0-9_]*)['\"]\\s*,\\s*['\"](?!COLOCAR_|change-me)[^'\"]+['\"]/i",
            $line,
            $match
        )) {
            if (str_ends_with(strtoupper($match[1]), '_URL')) {
                continue;
            }
            $findings[] = sprintf(
                '%s:%d (%s)',
                ltrim(str_replace($root, '', $path), '/'),
                $index + 1,
                $match[1]
            );
        }
    }
}

if ($findings !== []) {
    fwrite(STDERR, "Possíveis segredos literais encontrados (valores ocultados):\n");
    foreach ($findings as $finding) {
        fwrite(STDERR, "- {$finding}\n");
    }
    exit(1);
}

echo "OK: nenhum segredo literal detectado.\n";
