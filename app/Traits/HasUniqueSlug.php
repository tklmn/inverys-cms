<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUniqueSlug
{
    public static function bootHasUniqueSlug(): void
    {
        static::creating(function ($model) {
            $source = $model->getSlugSource();
            $column = $model->getSlugColumn();

            if (empty($model->{$column})) {
                $model->{$column} = Str::slug($model->{$source});
            }

            $original = $model->{$column};
            $counter = 0;
            $query = method_exists($model, 'trashed')
                ? static::withTrashed()
                : static::query();

            while ((clone $query)->where($column, $model->{$column})->exists()) {
                $counter++;
                $model->{$column} = $original.'-'.$counter;
            }
        });
    }

    public function getSlugColumn(): string
    {
        return 'slug';
    }

    abstract public function getSlugSource(): string;
}
