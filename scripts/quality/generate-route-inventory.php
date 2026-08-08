<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$definitions = array_merge(
    require $root . '/routes/web.php',
    require $root . '/routes/api.php'
);

usort($definitions, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

$lines = [
    '# Inventário automático de rotas',
    '',
    '> Arquivo gerado por `npm run docs:routes`. Não editar manualmente.',
    '',
    '| Métodos | Caminho | Nome | Handler | Middlewares |',
    '|---|---|---|---|---|',
];

foreach ($definitions as $definition) {
    $lines[] = sprintf(
        '| `%s` | `%s` | `%s` | `%s` | `%s` |',
        implode(', ', array_map('strtoupper', $definition['methods'])),
        $definition['path'],
        $definition['name'],
        $definition['target'],
        implode(', ', $definition['middleware'] ?? []) ?: 'nenhum'
    );
}

$lines[] = '';
$lines[] = sprintf('Total: **%d rotas declaradas**.', count($definitions));
$lines[] = '';

$destination = $root . '/documentacao/migracao-vercel/ROTAS.md';
file_put_contents($destination, implode(PHP_EOL, $lines));
printf("OK: inventário atualizado em %s.\n", $destination);
