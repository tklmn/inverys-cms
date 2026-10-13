<?php

namespace Tests\Unit\Services;

use App\Services\PackageInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class PackageInstallerTest extends TestCase
{
    use RefreshDatabase;

    private PackageInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installer = new PackageInstaller;
    }

    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(PackageInstaller::class, $this->installer);
    }

    public function test_theme_install_requires_theme_json(): void
    {
        $zipPath = $this->createZipWithFiles(['style.css' => 'body {}']);
        $file = new UploadedFile($zipPath, 'theme.zip', 'application/zip', null, true);

        $result = $this->installer->installTheme($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('theme.json', $result['error']);

        @unlink($zipPath);
    }

    public function test_plugin_install_requires_plugin_json(): void
    {
        $zipPath = $this->createZipWithFiles(['readme.txt' => 'Hello']);
        $file = new UploadedFile($zipPath, 'plugin.zip', 'application/zip', null, true);

        $result = $this->installer->installPlugin($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('plugin.json', $result['error']);

        @unlink($zipPath);
    }

    public function test_rejects_zip_with_path_traversal(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'pkg_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('../../../etc/passwd', 'malicious');
        $zip->close();

        $file = new UploadedFile($zipPath, 'bad.zip', 'application/zip', null, true);

        $result = $this->installer->installTheme($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('path traversal', $result['error']);

        @unlink($zipPath);
    }

    public function test_rejects_zip_with_blocked_extension(): void
    {
        $zipPath = $this->createZipWithFiles(['exploit.phar' => '<?php evil();']);
        $file = new UploadedFile($zipPath, 'bad.zip', 'application/zip', null, true);

        $result = $this->installer->installTheme($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('forbidden extension', $result['error']);

        @unlink($zipPath);
    }

    public function test_rejects_zip_with_blocked_file(): void
    {
        $zipPath = $this->createZipWithFiles(['.env' => 'SECRET=true']);
        $file = new UploadedFile($zipPath, 'bad.zip', 'application/zip', null, true);

        $result = $this->installer->installTheme($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('forbidden file', $result['error']);

        @unlink($zipPath);
    }

    public function test_theme_json_requires_slug_field(): void
    {
        $manifest = json_encode(['name' => 'Test Theme']);
        $zipPath = $this->createZipWithFiles(['theme.json' => $manifest]);
        $file = new UploadedFile($zipPath, 'theme.zip', 'application/zip', null, true);

        $result = $this->installer->installTheme($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('slug', $result['error']);

        @unlink($zipPath);
    }

    public function test_plugin_json_requires_provider_field(): void
    {
        $manifest = json_encode(['slug' => 'test-plugin', 'name' => 'Test Plugin']);
        $zipPath = $this->createZipWithFiles(['plugin.json' => $manifest]);
        $file = new UploadedFile($zipPath, 'plugin.zip', 'application/zip', null, true);

        $result = $this->installer->installPlugin($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('provider', $result['error']);

        @unlink($zipPath);
    }

    public function test_successful_theme_install(): void
    {
        $manifest = json_encode([
            'slug' => 'test-installable',
            'name' => 'Test Installable Theme',
            'version' => '1.0.0',
        ]);
        $zipPath = $this->createZipWithFiles([
            'theme.json' => $manifest,
            'assets/style.css' => 'body { color: red; }',
        ]);

        $file = new UploadedFile($zipPath, 'theme.zip', 'application/zip', null, true);

        $result = $this->installer->installTheme($file);

        $this->assertTrue($result['success']);
        $this->assertSame('test-installable', $result['slug']);

        // Clean up installed theme
        $targetDir = config('cms.themes_path').'/test-installable';
        if (is_dir($targetDir)) {
            File::deleteDirectory($targetDir);
        }
        @unlink($zipPath);
    }

    /**
     * Helper: create a ZIP file containing the given files.
     */
    private function createZipWithFiles(array $files): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'pkg_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $zipPath;
    }
}
