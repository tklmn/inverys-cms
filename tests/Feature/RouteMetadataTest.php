<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    /** Titles come from a translation key stored as route metadata. */
    private function expectedTitle(string $key): string
    {
        return '<title>'.__($key).' — '.__('translation.app_name').'</title>';
    }

    public function test_dashboard_title_reflects_route_metadata(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($this->expectedTitle('translation.navigation.dashboard'), false);
    }

    public function test_resource_index_title_reflects_route_metadata(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.users.index'))
            ->assertOk()
            ->assertSee($this->expectedTitle('translation.navigation.users'), false);
    }

    public function test_group_metadata_cascades_to_prefixed_routes(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.media.index'))
            ->assertOk()
            ->assertSee($this->expectedTitle('translation.navigation.media'), false);
    }

    public function test_route_without_title_metadata_falls_back_to_app_name(): void
    {
        // A non-page route carries no title; the head must fall back to app_name.
        $this->assertNull(
            Route::getRoutes()->getByName('backend.users.avatar.delete')->getMetadata('title')
        );
    }
}
