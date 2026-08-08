<?php

declare(strict_types=1);

namespace App\Core;

final class RouteDefinition
{
    public function __construct(
        public array $methods,
        public string $path,
        public string $target,
        public string $name,
        public array $middleware = []
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
    }
}
