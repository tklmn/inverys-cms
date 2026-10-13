<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageCategory extends Model
{
    use HasFactory, HasTranslations;

    /** Editable per language (multilingual plugin) */
    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'name', 'slug', 'description',
    ];

    /**
     * The pages that belong to the category.
     */
    public function pages()
    {
        return $this->belongsToMany(Page::class, 'page_category_page', 'page_category_id', 'page_id');
    }
}
