<?php

namespace Plugins\Blog\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

#[Middleware('permission:blog-settings')]
class SettingsController extends Controller
{
    protected const BOOLEAN_FIELDS = [
        'show_pagination',
        'show_author',
        'show_date',
        'show_categories',
        'show_excerpt',
        'show_featured_image',
        'show_read_more',
        'show_breadcrumbs',
        'show_prev_next',
        'show_sidebar',
        'show_rss_link',
    ];

    protected const DEFAULTS = [
        'url_prefix' => 'blog',
        'posts_per_page' => '10',
        'columns' => '1',
        'sort_by' => 'published_at',
        'sort_direction' => 'desc',
        'show_pagination' => '1',
        'show_author' => '1',
        'show_date' => '1',
        'show_categories' => '1',
        'show_excerpt' => '1',
        'show_featured_image' => '1',
        'show_read_more' => '1',
        'show_breadcrumbs' => '1',
        'show_prev_next' => '1',
        'show_sidebar' => '1',
        'show_rss_link' => '1',
        'read_more_text' => '',
    ];

    public function edit(): View
    {
        $rawSettings = cms()->settings()->getByPrefix('blog.');
        // Re-key with full 'blog.' prefix to match view expectations
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings["blog.{$key}"] = $value;
        }

        return view('blog-admin::settings', [
            'settings' => $settings,
            'defaults' => self::DEFAULTS,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            // Reserved first path segments: the blog routes are registered before
            // the core routes and would hide e.g. /backend/…
            'url_prefix' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/', Rule::notIn([
                trim((string) config('cms.backend_prefix', 'backend'), '/') ?: 'backend',
                'backend', 'api', 'install', 'storage', 'build', 'vendor', 'protected-assets', 'up',
                'theme', 'captcha', 'login', 'logout', 'register', 'forgot-password', 'reset-password',
                'verify-email', 'confirm-password', 'two-factor-challenge', 'two-factor-resend',
                'email', 'password', 'mail-providers', 'profile', 'search', 'forms', 'css', 'js',
                'images', 'fonts',
            ])],
            'posts_per_page' => 'required|integer|min:1|max:100',
            'columns' => 'required|integer|min:1|max:6',
            'sort_by' => 'required|in:published_at,title,created_at',
            'sort_direction' => 'required|in:asc,desc',
            'read_more_text' => 'nullable|string|max:100',
        ]);

        // Update URL prefix and sync the blog nav page
        $oldPrefix = cms()->settings()->get('blog.url_prefix', 'blog');
        $newPrefix = $request->input('url_prefix');

        // The blog routes come before the pages: a page with this slug would
        // no longer be reachable. Exception: the blog's own menu entry
        // (link to the prefix), which is updated along with it below.
        if ($newPrefix !== $oldPrefix && ($clash = cms()->pages()->findBySlug($newPrefix))) {
            $blogLinks = ['/'.$oldPrefix, url('/'.$oldPrefix), '/'.$newPrefix, url('/'.$newPrefix)];
            if (! in_array($clash->external_url, $blogLinks, true)) {
                return back()->withInput()->withErrors([
                    'url_prefix' => __('blog::blog.url_prefix_taken', ['title' => $clash->title]),
                ]);
            }
        }

        cms()->settings()->set('blog.url_prefix', $newPrefix, 'string');

        if ($oldPrefix !== $newPrefix) {
            $blogPage = cms()->pages()->findBySlug($oldPrefix);
            // The path is stored, the full address is read back — both count
            if ($blogPage && in_array($blogPage->external_url, ['/'.$oldPrefix, url('/'.$oldPrefix)], true)) {
                cms()->pages()->updateBySlug($oldPrefix, [
                    'slug' => $newPrefix,
                    'external_url' => '/'.$newPrefix,
                ]);
            }
        }

        cms()->settings()->set('blog.posts_per_page', $request->input('posts_per_page'), 'integer');
        cms()->settings()->set('blog.columns', $request->input('columns'), 'integer');
        cms()->settings()->set('blog.sort_by', $request->input('sort_by'), 'string');
        cms()->settings()->set('blog.sort_direction', $request->input('sort_direction'), 'string');

        foreach (self::BOOLEAN_FIELDS as $field) {
            cms()->settings()->set("blog.{$field}", $request->boolean($field) ? '1' : '0', 'boolean');
        }

        cms()->settings()->set('blog.read_more_text', $request->input('read_more_text') ?? '', 'string');

        return redirect()
            ->route('backend.blog.settings')
            ->with('success', __('blog::blog.settings_saved'));
    }
}
