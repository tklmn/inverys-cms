<?php

namespace App\Search\Admin;

use App\Contracts\AdminSearchProvider;
use App\Models\User;
use App\Services\AdminSearch;

/** Users by name and email address */
class UserAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'translation.admin_search.users';
    }

    public function icon(): string
    {
        return 'user-round';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('show-user')) {
            return [];
        }

        $users = AdminSearch::whereWords(User::query(), $query, ['name', 'first_name', 'last_name', 'email'])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'first_name', 'last_name', 'email']);

        foreach ($users as $found) {
            $full = trim($found->first_name.' '.$found->last_name);

            yield [
                'title' => $full !== '' ? $full : (string) $found->name,
                'url' => route('backend.users.show', $found),
                'context' => (string) $found->email,
            ];
        }
    }
}
