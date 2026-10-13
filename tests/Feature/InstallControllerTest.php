<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Tests\Concerns\PreservesInstalledMarker;
use Tests\TestCase;

/**
 * Feature coverage for the web installer's non-destructive endpoints.
 *
 * The installer's write steps (env, key, database, storage, finalize) mutate the
 * real filesystem (.env, migrations, the installed marker), so they are NOT
 * exercised here — driving them would clobber the live installation the suite
 * runs against. What is covered end-to-end: the install guard, requirement
 * rendering, save-time validation, state persistence/round-trip, the optional
 * step list, and the database connection tester.
 */
class InstallControllerTest extends TestCase
{
    use PreservesInstalledMarker;

    private function statePath(): string
    {
        // Filesystem-relative so the #[Before]/#[After] hooks can use it before
        // the application is bootstrapped.
        return dirname(__DIR__, 2).'/storage/framework/install-state.json';
    }

    /** Simulate a fresh install so the install.guard lets requests through. */
    private function markNotInstalled(): void
    {
        $path = $this->installedMarkerPath();
        if (file_exists($path)) {
            unlink($path);
        }
    }

    #[Before]
    protected function snapshotInstallState(): void
    {
        // Remove any leftover install-state file so tests start clean; it is
        // restored/cleaned by the matching after hook.
        if (file_exists($this->statePath())) {
            unlink($this->statePath());
        }
    }

    #[After]
    protected function cleanInstallState(): void
    {
        if (file_exists($this->statePath())) {
            unlink($this->statePath());
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'db_driver' => 'sqlite',
            'site_name' => 'Test Site',
            'site_url' => 'https://example.test',
            'locale' => 'en',
            'admin_first_name' => 'Ada',
            'admin_last_name' => 'Admin',
            'admin_name' => 'Ada Admin',
            'admin_email' => 'ada@example.test',
            'admin_password' => 'Super-secret-pw1',
        ], $overrides);
    }

    // ─── Guard ──────────────────────────────────────────────

    public function test_install_page_redirects_home_when_already_installed(): void
    {
        file_put_contents($this->installedMarkerPath(), json_encode(['version' => 'test']));

        $this->get('/install')->assertRedirect(url('/'));
    }

    public function test_install_page_renders_when_not_installed(): void
    {
        $this->markNotInstalled();

        $this->get('/install')
            ->assertOk()
            ->assertViewIs('install.wizard')
            ->assertViewHas('requirements')
            ->assertViewHas('permissions');
    }

    // ─── State endpoint ─────────────────────────────────────

    public function test_state_reports_not_started_without_state_file(): void
    {
        $this->markNotInstalled();

        $this->getJson('/install/state')->assertOk()->assertJson(['started' => false]);
    }

    // ─── Save + validation ──────────────────────────────────

    public function test_save_rejects_invalid_input(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/save', [
            'db_driver' => 'oracle',      // not in allowed list
            'site_url' => 'not-a-url',
            'admin_email' => 'nope',
            'admin_password' => 'short',  // too short and without character variety
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'db_driver', 'site_name', 'site_url', 'locale',
                'admin_first_name', 'admin_last_name', 'admin_name',
                'admin_email', 'admin_password',
            ]);

        $this->assertFileDoesNotExist($this->statePath());
    }

    public function test_admin_password_follows_the_default_policy(): void
    {
        $this->markNotInstalled();

        // Long enough, but without uppercase, digit and special character
        $this->postJson('/install/save', $this->validPayload(['admin_password' => 'longenoughpassword']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_password']);

        $this->assertFileDoesNotExist($this->statePath());
    }

    public function test_save_persists_state_and_hashes_password(): void
    {
        $this->markNotInstalled();

        $response = $this->postJson('/install/save', $this->validPayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        // A default (no demo content) install produces exactly the core steps.
        $stepIds = array_column($response->json('steps'), 'id');
        $this->assertSame(['env', 'key', 'database', 'admin', 'storage', 'finalize'], $stepIds);

        // State file is written with the password hashed — never in plaintext.
        $this->assertFileExists($this->statePath());
        $state = json_decode(file_get_contents($this->statePath()), true);
        $this->assertArrayHasKey('admin_password_hash', $state['config']);
        $this->assertArrayNotHasKey('admin_password', $state['config']);
        $this->assertStringNotContainsString('Super-secret-pw1', file_get_contents($this->statePath()));
        $this->assertTrue(password_verify('Super-secret-pw1', $state['config']['admin_password_hash']));
    }

    public function test_save_includes_optional_seed_steps_when_selected(): void
    {
        $this->markNotInstalled();

        $response = $this->postJson('/install/save', $this->validPayload([
            'seed_pages' => '1',
            'seed_categories' => '1',
            'seed_blog' => '1',
        ]))->assertOk();

        $stepIds = array_column($response->json('steps'), 'id');
        $this->assertSame(
            ['env', 'key', 'database', 'admin', 'pages', 'categories', 'blog', 'storage', 'finalize'],
            $stepIds
        );
    }

    public function test_state_round_trips_after_save(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/save', $this->validPayload())->assertOk();

        $this->getJson('/install/state')
            ->assertOk()
            ->assertJson(['started' => true])
            ->assertJsonStructure(['started', 'steps', 'completed']);
    }

    public function test_install_page_does_not_expose_the_saved_state(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/save', $this->validPayload([
            'db_driver' => 'mysql',
            'db_host' => 'db.internal.example',
            'db_username' => 'cms_user',
            'db_password' => 'Db-Secret-Pw-42',
        ]))->assertOk();
        $hash = json_decode(file_get_contents($this->statePath()), true)['config']['admin_password_hash'];

        // A stalled installation is publicly reachable —
        // the page may only show the progress, no credentials
        $response = $this->get('/install')->assertOk();

        $response->assertDontSee('Db-Secret-Pw-42', false);
        $response->assertDontSee('admin_password_hash', false);
        $response->assertDontSee(json_encode($hash), false);
        $response->assertDontSee('db.internal.example', false);
        $response->assertDontSee('cms_user', false);
        $response->assertDontSee('ada@example.test', false);
        $this->assertTrue($response->viewData('state')['started']);
        $this->assertSame(['started', 'steps', 'completed', 'error'], array_keys($response->viewData('state')));
    }

    // ─── Run step guard ─────────────────────────────────────

    public function test_run_step_without_state_fails(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/step', [])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ─── Database tester ────────────────────────────────────

    public function test_test_database_succeeds_for_sqlite(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/test-db', ['driver' => 'sqlite'])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'version', 'databases']);
    }

    public function test_test_database_reports_failure_for_bad_connection(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/test-db', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',            // nothing listening here
            'username' => 'nobody',
            'password' => 'wrong',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }
}
