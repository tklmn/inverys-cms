<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\InjectPluginHooks;
use App\Models\Revision;
use App\Support\Revisionables;
use App\Support\RevisionDiff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Revisions of every registered content type (App\Support\Revisionables):
 * pages in the core, blog posts and plugin content.
 * Addresses: backend/revisions/{type}/{id}/… — pages additionally under
 * backend/pages/{id}/revisions/… (type = page).
 */
class RevisionController extends Controller
{
    public function __construct(private Revisionables $types) {}

    /**
     * Revisions of a content item (JSON for the editor), each with language and a
     * summary of what was changed afterwards.
     */
    public function index(Request $request): JsonResponse
    {
        [$model, $options] = $this->resolve($request);

        $revisions = $model->revisions()->with('user:id,name')->get();
        $after = $this->successors($model, $revisions);
        $beforeRestore = array_unique([__('translation.pages.revisions.before_restore'), __('translation.pages.revisions.before_restore', [], 'en')]);

        $list = $revisions->map(function (Revision $r) use ($model, $after, $options, $beforeRestore) {
            $locale = RevisionDiff::locale($r);
            $diff = RevisionDiff::fields($model, $r->data ?? [], $after[$r->id], $locale, $options['labels']);

            return [
                'id' => $r->id,
                'user' => $r->user?->name ?? __('translation.pages.revisions.system'),
                'note' => $r->note,
                'locale' => $locale !== null ? strtoupper($locale) : null,
                'before_restore' => in_array($r->note, $beforeRestore, true),
                'summary' => RevisionDiff::summary($diff),
                'created_at' => format_datetime($r->created_at),
                'created_at_diff' => $r->created_at->diffForHumans(),
                'previewable' => $locale === null && is_callable($options['preview']),
            ];
        });

        return response()->json([
            'revisions' => $list,
            'count' => $list->count(),
        ]);
    }

    /**
     * A revision in comparison — with the current state (default: what
     * restoring would change) or with the next version (what was
     * changed afterwards).
     */
    public function show(Request $request): JsonResponse
    {
        [$model, $options, $revision] = $this->resolve($request, true);

        $locale = RevisionDiff::locale($revision);
        $againstNext = $request->query('against') === 'next';

        $target = $againstNext
            ? $this->successors($model, $model->revisions()->get())[$revision->id]
            : RevisionDiff::current($model, $locale);

        // Direction always "this version" → comparison state
        $diff = RevisionDiff::fields($model, $revision->data ?? [], $target, $locale, $options['labels']);

        return response()->json([
            'id' => $revision->id,
            'user' => $revision->user?->name,
            'note' => $revision->note,
            'locale' => $locale !== null ? strtoupper($locale) : null,
            'created_at' => format_datetime($revision->created_at),
            'against' => $againstNext ? 'next' : 'current',
            'from_label' => __('translation.pages.revisions.this_version'),
            'to_label' => $againstNext ? __('translation.pages.revisions.next_version') : __('translation.pages.revisions.current_version'),
            'diff' => $diff,
            'diff_count' => count($diff),
        ]);
    }

    /**
     * The content as it looked in the revision (original language) — view
     * only, nothing is saved. Only available if the content type ships
     * a preview.
     */
    public function preview(Request $request): Response
    {
        [$model, $options, $revision, $type] = $this->resolve($request, true);
        abort_if(RevisionDiff::locale($revision) !== null || ! is_callable($options['preview']), 404);

        // Notice in the interface language — the preview then switches to the content's language
        $notice = [
            'text' => __('translation.pages.revisions.preview_banner', ['date' => format_datetime($revision->created_at)]),
            'back' => __('translation.pages.revisions.preview_back'),
            'url' => $this->types->editUrl($type, $model, ['tab' => 'revisions']) ?? url()->previous(),
        ];

        // Copy with the revision's values; nothing is saved
        $snapshot = clone $model;
        foreach (array_intersect_key($revision->data ?? [], array_flip($model->getRevisionableFields())) as $field => $value) {
            $snapshot->setAttribute($field, $value);
        }

        $html = (string) ($options['preview'])($snapshot, $model);

        $banner = view('backend.partials.revision-preview-banner', ['notice' => $notice])->render();
        $html = str_contains($html, '</body>') ? InjectPluginHooks::insertBefore($html, '</body>', $banner, true) : $html.$banner;

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Restore a revision — the current state is saved as a revision first.
     */
    public function restore(Request $request): RedirectResponse
    {
        [$model, $options, $revision, $type] = $this->resolve($request, true);

        // The type's own rules for the state it would return to (e.g. a post
        // that becomes published again needs publish-post, not only edit-post)
        if (is_callable($options['authorize'])) {
            ($options['authorize'])($model, $revision->data ?? []);
        }

        $model->restoreFromRevision($revision);

        $url = $this->types->editUrl($type, $model, ['tab' => 'revisions', 'lang' => RevisionDiff::locale($revision)]);

        return ($url ? redirect($url) : back())->with('success', __('translation.pages.revisions.restored'));
    }

    /**
     * Delete a single revision.
     */
    public function destroy(Request $request): JsonResponse
    {
        [, , $revision] = $this->resolve($request, true);

        $revision->delete();

        return response()->json([
            'ok' => true,
            'message' => __('translation.pages.revisions.deleted'),
        ]);
    }

    /**
     * Content, type options and possibly the revision from the route; check permissions,
     * the revision must belong to the content. From the route instead of as arguments:
     * pages have "type" as a fixed value, which Laravel would pass as the last argument.
     *
     * @return array{0: Model, 1: array<string, mixed>, 2: ?Revision, 3: string}
     */
    private function resolve(Request $request, bool $withRevision = false): array
    {
        $type = (string) $request->route('type');
        $options = $this->types->get($type);
        abort_if($options === null, 404);
        abort_if($options['permission'] !== null && ! $request->user()?->hasPermission($options['permission']), 403);

        /** @var Model $model */
        $model = $options['model']::query()->findOrFail($request->route('id'));

        $revision = null;
        if ($withRevision) {
            $revision = Revision::query()->findOrFail($request->route('revision'));
            if ($revision->revisionable_type !== $model::class || (string) $revision->revisionable_id !== (string) $model->getKey()) {
                abort(404);
            }
        }

        return [$model, $options, $revision, $type];
    }

    /**
     * The state after each revision: the next newer revision of the same
     * language, for the newest one the current state.
     *
     * @param  Collection<int, Revision>  $revisions  newest first
     * @return array<int, array>
     */
    private function successors(Model $model, Collection $revisions): array
    {
        $newer = [];
        $after = [];
        foreach ($revisions as $revision) {
            $locale = RevisionDiff::locale($revision) ?? '';
            $after[$revision->id] = $newer[$locale] ?? RevisionDiff::current($model, $locale !== '' ? $locale : null);
            $newer[$locale] = $revision->data ?? [];
        }

        return $after;
    }
}
