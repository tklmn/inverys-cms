<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\RedirectIfInstalled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The web installer is reachable without signing in. It must stay locked
 * once the CMS database is set up — even if the marker file
 * storage/installed gets lost (e.g. storage/ not persistent).
 */
class InstallerLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    public function test_installer_redirects_when_marker_exists(): void
    {
        $this->get('/install')->assertRedirect(url('/'));
        $this->postJson('/install/save', [])->assertRedirect(url('/'));
    }

    public function test_installer_stays_locked_when_marker_is_missing_but_users_exist(): void
    {
        User::factory()->create();
        $this->fakeMissingMarker(installInProgress: false);

        $this->get('/install/state')->assertForbidden();
        $this->postJson('/install/save', ['db_driver' => 'sqlite'])->assertForbidden();
        $this->postJson('/install/step', ['step' => 'env'])->assertForbidden();
    }

    public function test_interrupted_installation_can_be_resumed(): void
    {
        User::factory()->create();
        $this->fakeMissingMarker(installInProgress: true);

        $this->getJson('/install/state')->assertOk();
    }

    public function test_installer_is_open_on_an_empty_database(): void
    {
        $this->fakeMissingMarker(installInProgress: false);

        $this->getJson('/install/state')->assertOk();
    }

    /**
     * Only the installer guard sees the marker as missing — the real file stays
     * untouched (other tests and the development site need it).
     */
    private function fakeMissingMarker(bool $installInProgress): void
    {
        $this->app->bind(RedirectIfInstalled::class, fn () => new class($installInProgress) extends RedirectIfInstalled
        {
            public function __construct(private bool $inProgress) {}

            protected function markerExists(): bool
            {
                return false;
            }

            protected function installInProgress(): bool
            {
                return $this->inProgress;
            }
        });
    }
}
