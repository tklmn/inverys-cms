<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Tests\TestCase;

/**
 * Error pages in the website's style (resources/views/errors).
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_page_shows_the_styled_404(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee(__('translation.errors.title_404'))
            ->assertSee('site-error', false)
            ->assertSee('name="robots" content="noindex"', false)
            ->assertSee('role="search"', false);
    }

    public function test_every_status_has_a_view_with_texts(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = view('errors.'.$code)->render();

            $this->assertStringContainsString((string) $code, $html);
            $this->assertStringContainsString(__('translation.errors.title_'.$code), $html);
            $this->assertStringContainsString(__('translation.errors.text_'.$code), $html);
        }
    }

    public function test_error_page_renders_without_a_build(): void
    {
        // Missing manifest (e.g. during a deployment): the error page stays
        // our own instead of falling back to Symfony's emergency page.
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null)
            {
                throw new ViteManifestNotFoundException('Vite manifest not found');
            }
        });

        $html = view('errors.500')->render();

        $this->assertStringContainsString(__('translation.errors.title_500'), $html);
        $this->assertStringContainsString('site-error-code', $html);
    }
}
