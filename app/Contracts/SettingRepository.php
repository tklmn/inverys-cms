<?php

namespace App\Contracts;

interface SettingRepository
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void;

    public function has(string $key): bool;

    public function getByPrefix(string $prefix): array;

    public function delete(string $key): bool;
}
