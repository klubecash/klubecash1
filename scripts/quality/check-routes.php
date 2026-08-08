<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$definitions = array_merge(
    require $root . '/routes/web.php',
    require $root . '/routes/api.php'
);
$missing = [];
$names = [];
$keys = [];

foreach ($definitions as $definition) {
    $target = $definition['target'];
    if (!is_file($root . '/' . $target)) {
        $missing[] = $target;
    }

    if (isset($names[$definition['name']])) {
        $missing[] = 'nome duplicado: ' . $definition['name'];
    }
    $names[$definition['name']] = true;

    foreach ($definition['methods'] as $method) {
        $key = strtoupper($method) . ' ' . $definition['path'];
        if (isset($keys[$key])) {
            $missing[] = 'rota duplicada: ' . $key;
        }
        $keys[$key] = true;
    }
}

if ($missing !== []) {
    foreach ($missing as $target) {
        fwrite(STDERR, "Rota aponta para arquivo inexistente: {$target}\n");
    }
    exit(1);
}

printf("OK: %d rotas declaradas e todos os destinos existem.\n", count($definitions));
