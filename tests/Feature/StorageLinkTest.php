<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Upload via FTP: the public/storage link does not come along — the CMS
 * creates it itself on the first request (relative, so it works on any server).
 */
class StorageLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        // Own public directory: the installation's real link stays untouched
        $this->public = sys_get_temp_dir().'/inverys-public-'.bin2hex(random_bytes(4));
        mkdir($this->public);
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        if (is_link($this->public.'/storage')) {
            unlink($this->public.'/storage');
        }
        @rmdir($this->public);

        parent::tearDown();
    }

    public function test_a_missing_storage_link_is_created_relatively(): void
    {
        $this->get(route('login'))->assertOk();

        $this->assertTrue(is_link($this->public.'/storage'));
        $this->assertSame('../storage/app/public', readlink($this->public.'/storage'));
    }

    public function test_an_existing_storage_folder_is_left_alone(): void
    {
        mkdir($this->public.'/storage');

        $this->get(route('login'))->assertOk();

        $this->assertFalse(is_link($this->public.'/storage'));
        rmdir($this->public.'/storage');
    }
}
