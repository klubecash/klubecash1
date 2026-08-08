<?php

declare(strict_types=1);

namespace App\Core;

final class RouteMatch
{
    public function __construct(
        public RouteDefinition $route,
        public array $parameters = []
    ) {
    }
}
