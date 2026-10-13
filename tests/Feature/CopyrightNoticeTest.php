<?php

namespace Tests\Feature;

use App\Http\Middleware\AddCopyrightNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Copyright notice in the source: on every HTML page right after the
 * doctype, with the current year — on error pages too, never in JSON.
 */
class CopyrightNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    public function test_pages_carry_the_notice_right_after_the_doctype(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/^<!DOCTYPE html>\n<!-- Copyright © '.now()->year.' Inverys · Inverys CMS -->/i', $html);
        $this->assertSame(1, substr_count($html, 'Copyright ©'));
    }

    public function test_error_pages_carry_the_notice_too(): void
    {
        $this->get('/this-page-does-not-exist-'.uniqid())
            ->assertNotFound()
            ->assertSee(AddCopyrightNotice::notice(), false);
    }

    public function test_the_year_follows_the_calendar(): void
    {
        Carbon::setTestNow('2031-01-01 00:00:01');

        $this->assertSame('<!-- Copyright © 2031 Inverys · Inverys CMS -->', AddCopyrightNotice::notice());

        Carbon::setTestNow();
    }

    public function test_json_and_fragments_are_left_alone(): void
    {
        $json = AddCopyrightNotice::apply(response()->json(['ok' => true]));
        $this->assertStringNotContainsString('Copyright', $json->getContent());

        $fragment = AddCopyrightNotice::apply(new Response('<div>Block</div>', 200, ['Content-Type' => 'text/html']));
        $this->assertSame('<div>Block</div>', $fragment->getContent());
    }

    public function test_a_theme_cannot_drop_it(): void
    {
        // Whatever a theme outputs does not matter: the notice is added afterwards
        $themed = AddCopyrightNotice::apply(new Response('<!doctype html><html><body>Theme</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $this->assertStringStartsWith('<!doctype html>'."\n".AddCopyrightNotice::notice(), $themed->getContent());
    }
}
