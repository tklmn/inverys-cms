<?php

namespace Plugins\FormBuilder\Search;

use App\Contracts\SearchProvider;
use App\Helpers\SearchHelper;
use Illuminate\Support\Collection;
use Plugins\FormBuilder\Models\Form;

class FormSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'forms';
    }

    public function label(): string
    {
        return 'form-builder::forms.forms';
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'translation.settings.search_field_title', 'default' => 'on'],
            'description' => ['label' => 'form-builder::forms.description', 'default' => 'on'],
        ];
    }

    public function search(string $query, array $fieldFlags): Collection
    {
        // The results lead to the editor in the backend — website visitors
        // should not see them, only those allowed to edit the entry
        if (! auth()->user()?->hasPermission('edit-form')) {
            return collect();
        }

        $search = SearchHelper::escapeLike($query);

        return Form::where('is_active', true)
            ->where(function ($q) use ($search, $fieldFlags) {
                if ($fieldFlags['name'] ?? false) {
                    $q->orWhere('name', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['description'] ?? false) {
                    $q->orWhere('description', 'LIKE', "%{$search}%");
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($form) => (object) [
                'title' => $form->name,
                'description' => $form->description ?: '',
                'url' => route('backend.forms.edit', $form),
                'type' => 'form',
            ]);
    }
}
