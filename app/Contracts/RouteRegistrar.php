<?php

namespace App\Contracts;

interface RouteRegistrar
{
    public function web(string $prefix, callable $routes): void;

    public function backend(string $prefix, callable $routes): void;

    public function api(string $prefix, callable $routes): void;

    public function apiProtected(string $prefix, callable $routes): void;
}
