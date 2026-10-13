<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Results for the backend quick search (Ctrl/⌘ + K). Unlike
 * SearchProvider (website search) it also finds drafts and inactive
 * content and links into the editor.
 *
 * Register in the plugin's ServiceProvider:
 *   $this->extensions()->registerAdminSearchProvider(new MyAdminSearch);
 */
interface AdminSearchProvider
{
    /** Unique key of the group, e.g. "pages" or "blog-posts" */
    public function key(): string;

    /** Heading of the group in the quick search (translation key or text) */
    public function label(): string;

    /** Icon of the results (Lucide name, e.g. "file-text") */
    public function icon(): string;

    /**
     * Results for $query, at most $limit. The provider checks itself whether
     * $user may see the content and open the linked page — otherwise [].
     *
     * @return iterable<array{title: string, url: string, context?: ?string}>
     */
    public function search(string $query, User $user, int $limit): iterable;
}
