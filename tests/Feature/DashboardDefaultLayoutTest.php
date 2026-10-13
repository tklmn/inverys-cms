<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Default dashboard layout: the media widget is not part of it but sits in
 * the widget picker — whoever has added it keeps it.
 */
class DashboardDefaultLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    /** @return list<string> */
    private function placed(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach ($row['columns'] as $column) {
                foreach ($column as $item) {
                    $ids[] = $item['id'];
                }
            }
        }

        return $ids;
    }

    public function test_media_widget_is_offered_but_not_placed_by_default(): void
    {
        $response = $this->actingAs($this->createAdmin())->get(route('dashboard'))->assertOk();

        $this->assertNotContains('media-overview', $this->placed($response->viewData('rows')));
        $this->assertArrayHasKey('media-overview', $response->viewData('hiddenWidgets'));
        $this->assertContains('recent-pages', $this->placed($response->viewData('rows')));
    }

    public function test_a_saved_layout_keeps_the_media_widget(): void
    {
        $admin = $this->createAdmin();
        $admin->setSetting('dashboard_layout', json_encode(['rows' => [
            ['id' => 'row-1', 'layout' => '100', 'columns' => [[['id' => 'media-overview', 'width' => 100]]]],
        ], 'hidden' => []]));

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $this->assertContains('media-overview', $this->placed($response->viewData('rows')));
    }
}
