<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * One editor for all pages: HTML becomes a text block (model and
 * migration), "content" holds the search text of the blocks.
 */
class OneEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_becomes_a_readable_text_block(): void
    {
        $page = Page::create(['title' => 'Imprint', 'slug' => 'imprint', 'content_type' => 'html', 'content' => '<h2>Details</h2><p>Sample Street 1</p>']);

        $this->assertSame('blocks', $page->content_type);
        $this->assertSame('text', $page->blocks[0]['type']);
        $this->assertSame('readable', $page->blocks[0]['settings']['measure']);
        $this->assertSame('<h2>Details</h2><p>Sample Street 1</p>', $page->blocks[0]['data']['content']);
        $this->assertSame('Details Sample Street 1', $page->content);
    }

    public function test_deleting_all_blocks_does_not_bring_the_old_text_back(): void
    {
        $page = Page::create(['title' => 'Page', 'slug' => 'page', 'content_type' => 'html', 'content' => '<p>Old</p>']);

        $page->update(['blocks' => []]);

        $page->refresh();
        $this->assertSame([], $page->blocks);
        $this->assertSame('', $page->content);
    }

    public function test_blocks_set_without_content_type_are_kept(): void
    {
        // How a plugin or script creates a page: only blocks, no content_type
        $page = new Page(['title' => 'Form', 'slug' => 'form']);
        $page->blocks = [['id' => 'f', 'type' => 'heading', 'data' => ['text' => 'Contact', 'level' => 'h2'], 'settings' => []]];
        $page->save();

        $page->refresh();
        $this->assertSame('blocks', $page->content_type);
        $this->assertSame('heading', $page->blocks[0]['type']);
        $this->assertSame('Contact', $page->content);
    }

    public function test_empty_html_gives_no_block(): void
    {
        $page = Page::create(['title' => 'Link', 'slug' => 'link', 'content_type' => 'html', 'content' => '<p>&nbsp;</p>', 'external_url' => 'https://example.com']);

        $this->assertSame([], $page->blocks);
    }

    public function test_search_finds_text_inside_blocks(): void
    {
        $this->markAsInstalled();
        Page::create(['title' => 'Our Services', 'slug' => 'services', 'is_active' => true, 'include_in_search' => true, 'content_type' => 'blocks',
            'blocks' => [['id' => 'c', 'type' => 'card', 'data' => ['title' => 'Maintenance', 'content' => 'Around the clock']]]]);

        $this->get('/search?q=Maintenance')->assertOk()->assertSee('Our Services');
    }

    public function test_migration_converts_html_pages_and_their_translations(): void
    {
        $this->createTranslationsTable();

        $id = DB::table('pages')->insertGetId([
            'title' => 'Privacy', 'slug' => 'privacy', 'content_type' => 'html',
            'content' => '<p>Original</p>', 'blocks' => json_encode([['id' => 'x', 'type' => 'heading', 'data' => ['text' => 'Hidden']]]),
            'level' => 0, 'order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $blocksPage = DB::table('pages')->insertGetId([
            'title' => 'Start', 'slug' => 'start', 'content_type' => 'blocks', 'content' => '<p>outdated</p>',
            'blocks' => json_encode([['id' => 'h', 'type' => 'heading', 'data' => ['text' => 'Hello']]]),
            'level' => 0, 'order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ml_translations')->insert([
            'translatable_type' => Page::class, 'translatable_id' => $id, 'locale' => 'de', 'field' => 'content', 'value' => '<p>Translated</p>',
        ]);

        (require database_path('migrations/2026_09_28_160000_convert_html_pages_to_blocks.php'))->up();

        $page = DB::table('pages')->find($id);
        $blocks = json_decode($page->blocks, true);
        $this->assertSame('blocks', $page->content_type);
        $this->assertSame('<p>Original</p>', $blocks[0]['data']['content']);
        $this->assertSame('Original', $page->content);

        // The HTML page's invisible blocks are kept in a revision
        $revision = DB::table('revisions')->where('revisionable_id', $id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('Hidden', json_decode($revision->data, true)['blocks'][0]['data']['text']);

        // Translation: same text block (same ID), translated content, search text
        $translated = DB::table('ml_translations')->where(['translatable_id' => $id, 'locale' => 'de'])->pluck('value', 'field');
        $translatedBlocks = json_decode($translated['blocks'], true);
        $this->assertSame($blocks[0]['id'], $translatedBlocks[0]['id']);
        $this->assertSame('<p>Translated</p>', $translatedBlocks[0]['data']['content']);
        $this->assertSame('Translated', $translated['content']);

        // Block pages keep their blocks and get the search text
        $start = DB::table('pages')->find($blocksPage);
        $this->assertSame('Hello', $start->content);
        $this->assertSame('Hello', json_decode($start->blocks, true)[0]['data']['text']);
    }

    private function createTranslationsTable(): void
    {
        if (Schema::hasTable('ml_translations')) {
            return;
        }

        Schema::create('ml_translations', function ($table) {
            $table->id();
            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 12);
            $table->string('field', 64);
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }
}
