<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ContentTranslator;
use App\Http\Controllers\Controller;
use App\Models\Concerns\HasTranslations;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Copy from German" in the editor: the version of an existing
 * translation as a template for a new language.
 *
 * The same for all translatable models. Which model, which languages
 * and where to return are in a signed address that only the editor
 * issues (x-ui.translation-source) — whoever may not open the editor
 * therefore gets no address either. It is only valid for the user it
 * was issued to.
 */
class TranslationCopyController extends Controller
{
    /**
     * Edit permission per model type. Unknown types follow the
     * naming rule edit-<model> (e.g. Event → edit-event) — if that
     * permission does not exist, the action is blocked.
     */
    private const EDIT_PERMISSIONS = [
        'App\\Models\\Page' => 'edit-page',
        'App\\Models\\PageCategory' => 'edit-page-category',
        'App\\Models\\Media' => 'edit-media',
        'Plugins\\Blog\\Models\\Post' => 'edit-post',
        'Plugins\\Blog\\Models\\BlogCategory' => 'edit-blog-category',
        'Plugins\\FormBuilder\\Models\\Form' => 'edit-form',
        'Plugins\\Gallery\\Models\\GalleryAlbum' => 'edit-gallery-album',
    ];

    public function store(Request $request, Locales $locales, ContentTranslator $translator): RedirectResponse
    {
        abort_unless((int) $request->query('user') === (int) $request->user()?->getKey(), 403);

        $type = (string) $request->query('type');
        abort_unless(
            class_exists($type) && is_subclass_of($type, Model::class) && in_array(HasTranslations::class, class_uses_recursive($type), true),
            404,
        );

        // The address is valid for a few hours — whoever has lost the permission since may no longer use it
        $permission = self::EDIT_PERMISSIONS[$type] ?? 'edit-'.Str::kebab(class_basename($type));
        abort_unless($request->user()->hasPermission($permission), 403);

        $from = (string) $request->query('from');
        $to = (string) $request->query('to');
        abort_unless($locales->has($from) && $locales->has($to) && $from !== $to && $to !== $locales->base(), 404);

        /** @var Model $model */
        $model = $type::query()->findOrFail($request->query('id'));
        $copied = $translator->copy($model, $from, $to);

        $back = (string) $request->query('back');
        $redirect = str_starts_with($back, '/') && ! str_starts_with($back, '//')
            ? redirect()->to($back)
            : redirect()->back();

        return $copied > 0
            ? $redirect->with('success', trans_choice('translation.locales.copied', $copied, ['count' => $copied, 'language' => $locales->native($from)]))
            : $redirect->with('warning', __('translation.locales.copy_nothing', ['language' => $locales->native($from)]));
    }
}
