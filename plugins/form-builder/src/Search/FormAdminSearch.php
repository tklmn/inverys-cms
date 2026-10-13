<?php

namespace Plugins\FormBuilder\Search;

use App\Contracts\AdminSearchProvider;
use App\Models\User;
use App\Services\AdminSearch;
use Plugins\FormBuilder\Models\Form;

/** Backend quick search: forms by name, identifier and description */
class FormAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'forms';
    }

    public function label(): string
    {
        return 'form-builder::forms.forms';
    }

    public function icon(): string
    {
        return 'clipboard-list';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('edit-form')) {
            return [];
        }

        $found = AdminSearch::whereWords(Form::query(), $query, ['name', 'slug', 'description'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        foreach ($found as $item) {
            yield [
                'title' => (string) $item->name,
                'url' => route('backend.forms.edit', $item),
                'context' => $item->slug,
            ];
        }
    }
}
