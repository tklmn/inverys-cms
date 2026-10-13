<?php

namespace App\Contracts;

interface CmsUserProvider
{
    public function first(): mixed;

    public function find(int $id): mixed;

    public function currentUser(): mixed;

    public function currentUserId(): ?int;

    /**
     * All users as an [id => name] map, ordered by name — for filter/select inputs.
     *
     * @return array<int, string>
     */
    public function allForSelect(): array;
}
