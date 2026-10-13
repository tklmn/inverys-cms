<?php

namespace Tests\Feature\Security;

use App\Helpers\SearchHelper;
use App\Http\Middleware\InjectPluginHooks;
use App\Models\Page;
use App\Services\BlockRenderer;
use App\Services\BlockValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Broken or malicious input leads to an error message (422), never to a
 * server error — and never to an unreachable page.
 */
class InputHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_block_validator_rejects_non_objects_instead_of_crashing(): void
    {
        $validator = new BlockValidator;

        $this->assertFalse($validator->validate([1]));
        $this->assertFalse($validator->validate([['id' => 'a', 'type' => 'text', 'data' => 'x']]));
        $this->assertTrue($validator->validate([['id' => 'a', 'type' => 'text', 'data' => []]]));
    }

    public function test_renderer_skips_broken_entries(): void
    {
        $html = app(BlockRenderer::class)->render([1, 'x', ['id' => 'h', 'type' => 'heading', 'data' => ['text' => 'Hello', 'level' => 'h2']]]);

        $this->assertStringContainsString('Hello', $html);
    }

    public function test_save_and_preview_blocks_answer_422_for_broken_json(): void
    {
        $page = Page::factory()->create();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->postJson(route('backend.pages.save-blocks', $page), ['blocks' => '"x"'])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('backend.pages.save-blocks', $page), ['blocks' => '[1]'])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('backend.pages.preview-blocks'), ['blocks' => '[1]'])->assertStatus(422);
    }

    public function test_pages_cannot_take_system_addresses(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.pages.store'), ['title' => 'Login', 'slug' => 'login'])
            ->assertSessionHasErrors('slug');
    }

    public function test_unknown_categories_are_a_validation_error(): void
    {
        $page = Page::factory()->create();

        $this->actingAs($this->createAdmin())
            ->put(route('backend.pages.update', $page), ['title' => 'X', 'slug' => $page->slug, 'categories' => [999999]])
            ->assertSessionHasErrors('categories.0');
    }

    public function test_like_escaping_handles_backslash_and_arrays(): void
    {
        $this->assertSame('a\\\\b\\%c\\_', SearchHelper::escapeLike('a\\b%c_'));
        $this->assertSame('', SearchHelper::escapeLike(['x']));
    }

    public function test_hooks_go_before_the_real_closing_tags_only(): void
    {
        $html = '<html><head></head><body><script>var s = "</body>";</script></body></html>';

        $this->assertSame(
            '<html><head></head><body><script>var s = "</body>";</script>X</body></html>',
            InjectPluginHooks::insertBefore($html, '</body>', 'X', true),
        );
    }
}
