<?php

use App\Models\Page;
use App\Support\BlockText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One editor for all pages: HTML pages become a text block.
 *
 * - The body text moves losslessly into a text block (readable line width,
 *   as before). If an HTML page also had (invisible) blocks on the side,
 *   a revision saves the old state first.
 * - Translations (multilingual) move along: the translated text becomes
 *   the translated blocks.
 * - "content" then holds the readable text of the blocks for every page —
 *   so search finds text in block pages for the first time.
 *
 * Not reversible; the revisions and the text block contain everything needed.
 */
return new class extends Migration
{
    private const TRANSLATIONS = 'ml_translations';

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $translations = Schema::hasTable(self::TRANSLATIONS);

        foreach (DB::table('pages')->orderBy('id')->get() as $page) {
            $blocks = json_decode((string) $page->blocks, true);
            $blocks = is_array($blocks) ? $blocks : [];
            $html = (string) $page->content;
            // What the website showed so far: blocks only for "blocks" with content
            $showedHtml = $page->content_type !== 'blocks' || $blocks === [];
            $converted = false;

            if ($showedHtml) {
                if ($blocks !== [] && Schema::hasTable('revisions')) {
                    $this->keepRevision($page);
                }

                $blocks = Page::hasVisibleHtml($html) ? [Page::textBlock($html)] : [];
                $converted = true;
            }

            DB::table('pages')->where('id', $page->id)->update([
                'content_type' => 'blocks',
                'blocks' => json_encode($blocks),
                'content' => BlockText::extract($blocks),
            ]);

            if ($translations) {
                $this->convertTranslations($page->id, $converted, $blocks[0]['id'] ?? null);
            }
        }
    }

    public function down(): void
    {
        // Not reversible — the content is preserved losslessly in the text block
    }

    /** Save the old state before invisible blocks are replaced */
    private function keepRevision(object $page): void
    {
        $data = [];
        foreach ((new Page)->getRevisionableFields() as $field) {
            $data[$field] = property_exists($page, $field) ? $page->{$field} : null;
        }
        $data['blocks'] = json_decode((string) $page->blocks, true);

        DB::table('revisions')->insert([
            'revisionable_type' => Page::class,
            'revisionable_id' => $page->id,
            'user_id' => null,
            'data' => json_encode($data),
            'note' => 'Before switching to blocks',
            'created_at' => now(),
        ]);
    }

    /**
     * Translations of a page: translated text → translated blocks
     * (for converted pages), and per language the search text of the blocks.
     *
     * @param  bool  $converted  was the page an HTML page?
     * @param  string|null  $textId  ID of the text block in the original (same ID in every language)
     */
    private function convertTranslations(int $pageId, bool $converted, ?string $textId): void
    {
        $rows = DB::table(self::TRANSLATIONS)
            ->where('translatable_type', Page::class)
            ->where('translatable_id', $pageId)
            ->whereIn('field', ['content', 'blocks'])
            ->get()
            ->groupBy('locale');

        foreach ($rows as $locale => $fields) {
            $content = $fields->firstWhere('field', 'content');
            $blocksRow = $fields->firstWhere('field', 'blocks');
            $blocks = $blocksRow ? json_decode((string) $blocksRow->value, true) : null;

            if ($converted) {
                // Converted page: only the translated body text counts
                $html = $content ? (string) $content->value : '';
                $blocks = Page::hasVisibleHtml($html) ? [Page::textBlock($html, $textId)] : null;
            }

            $this->put($pageId, $locale, 'blocks', is_array($blocks) && $blocks !== [] ? json_encode($blocks) : null);
            $this->put($pageId, $locale, 'content', is_array($blocks) && $blocks !== [] ? BlockText::extract($blocks) : null);
        }
    }

    private function put(int $pageId, string $locale, string $field, ?string $value): void
    {
        $where = ['translatable_type' => Page::class, 'translatable_id' => $pageId, 'locale' => $locale, 'field' => $field];

        if ($value === null || $value === '') {
            DB::table(self::TRANSLATIONS)->where($where)->delete();

            return;
        }

        DB::table(self::TRANSLATIONS)->updateOrInsert($where, ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
    }
};
