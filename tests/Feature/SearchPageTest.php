<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Frontend search: a search term that is too short is not searched and gets
 * the minimum length hint — not "no results, search shorter".
 */
class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_too_short_query_shows_minimum_length_hint(): void
    {
        $this->get('/search?q=a')
            ->assertOk()
            ->assertSee(__('translation.frontend.search_min_chars', ['min' => 2]))
            ->assertDontSee(__('translation.frontend.search_no_results'));
    }

    public function test_query_without_hits_shows_no_results(): void
    {
        $this->get('/search?q=zzqxv')
            ->assertOk()
            ->assertSee(__('translation.frontend.search_no_results'))
            ->assertDontSee(__('translation.frontend.search_min_chars', ['min' => 2]));
    }
}
