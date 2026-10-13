<?php

namespace App\Contracts;

interface MediaManager
{
    public function store(string $path, string $content, string $disk = 'public'): string;

    public function createRecord(array $attributes): mixed;

    public function storeAndRecord(string $path, string $content, array $attributes, string $disk = 'public'): mixed;

    public function findByPath(string $path): mixed;

    public function delete(mixed $media): bool;
}
