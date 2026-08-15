<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$failed = [];
$checked = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $normalizedPath = str_replace('\\', '/', $path);
    if (preg_match('#/(?:node_modules|vendor|\.git)/#', $normalizedPath)) {
        continue;
    }

    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $exitCode);
    $checked++;

    if ($exitCode !== 0) {
        $failed[] = $normalizedPath . PHP_EOL . implode(PHP_EOL, $output);
    }
}

if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL . PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo "OK: {$checked} arquivos PHP passaram na validação de sintaxe." . PHP_EOL;
