<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionPruningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
    }

    public function test_pruning_keeps_the_newest_revisions_not_the_oldest(): void
    {
        $this->actingAs($this->createAdmin());
        Setting::updateOrCreate(
            ['key' => 'revisions.max_per_model'],
            ['value' => '3', 'type' => 'integer'],
        );

        $page = Page::factory()->create(['title' => 'v0']);

        // Each update snapshots the pre-update state, at a distinct, increasing time.
        $base = now();
        for ($i = 1; $i <= 6; $i++) {
            // Further apart than one editing session (cms.revisions.session_minutes)
            $this->travelTo($base->copy()->addMinutes($i * 15));
            $page->update(['title' => "v{$i}"]);
        }
        $this->travelBack();

        $revisions = $page->revisions()->get();

        // Capped at the configured maximum...
        $this->assertCount(3, $revisions);

        // ...and the retained snapshots are the most recent pre-update states
        // (v5, v4, v3 — relation is ordered newest-first), NOT the oldest ones.
        $this->assertSame(['v5', 'v4', 'v3'], $revisions->pluck('data.title')->all());
    }
}
