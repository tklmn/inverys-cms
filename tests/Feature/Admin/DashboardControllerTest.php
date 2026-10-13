<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = $this->createAdmin();
        Page::factory()->count(3)->create();
        Media::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_editor_can_view_dashboard(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_user_without_dashboard_permission_gets_403(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    // ── "System & security" widget ──────────────────────────────

    public function test_security_widget_is_in_the_default_layout_for_admins(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-id="system-security"', false)
            ->assertSee(route('backend.system.components'), false)
            ->assertSee(route('backend.system.sbom.download'), false);
    }

    public function test_security_widget_is_hidden_for_editors(): void
    {
        $this->actingAs($this->createEditor())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-id="system-security"', false)
            ->assertDontSee('data-picker="system-security"', false);
    }

    public function test_security_widget_is_offered_not_forced_into_saved_layouts(): void
    {
        $admin = $this->createAdmin();
        // Saved layout from before the widget existed
        $admin->setSetting('dashboard_layout', json_encode([
            'rows' => [['id' => 'row-1', 'layout' => '100', 'columns' => [[['id' => 'welcome', 'width' => 100]]]]],
            'hidden' => [],
        ]));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-id="system-security"', false)
            ->assertSee('data-picker="system-security"', false);
    }

    public function test_security_widget_warns_about_blocked_indexing_and_debug_mode(): void
    {
        Setting::updateOrCreate(['key' => 'seo.robots_allow_indexing'], ['value' => 'off']);
        config(['app.debug' => true]);

        $this->actingAs($this->createAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-health="indexing"', false)
            ->assertSee('data-health="debug"', false)
            ->assertSee('data-health="security_txt"', false)
            ->assertSee(__('translation.dashboard.health.indexing_title'));
    }

    public function test_security_widget_reports_no_issues_when_everything_is_fine(): void
    {
        // APP_URL stays: the HTTP check only applies in production, and a different
        // host would get stuck in Symfony's static list of trusted hosts
        config(['app.debug' => false]);

        $this->actingAs($this->createAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('translation.dashboard.security.all_good'))
            ->assertDontSee('data-health="indexing"', false)
            ->assertDontSee('data-health="debug"', false);
    }

    public function test_unauthenticated_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect();
    }
}
