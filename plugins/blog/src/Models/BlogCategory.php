<?php

namespace Plugins\Blog\Models;

use App\Models\Concerns\HasTranslations;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Plugins\ActivityLog\Traits\LogsActivity;

class BlogCategory extends Model
{
    use HasTranslations;
    use HasUniqueSlug;
    use LogsActivity;

    /** Fields that differ per language (Multilingual plugin) */
    protected array $translatable = ['name', 'slug', 'description'];

    public function getSlugSource(): string
    {
        return 'name';
    }

    protected $table = 'blog_categories';

    protected $fillable = [
        'name', 'slug', 'description',
    ];

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'blog_category_post', 'blog_category_id', 'post_id');
    }
}
