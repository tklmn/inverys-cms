<?php

namespace Tests\Feature\Security;

use App\Models\ApiToken;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The media library stores files with the client's extension. A real image
 * with a .html/.svg extension must therefore not get through — it would end up
 * under /storage/… and be served as HTML (without CSP): stored XSS.
 */
class UploadHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
    }

    public static function dangerousNames(): array
    {
        return [['polyglot.html'], ['polyglot.svg'], ['polyglot.htm'], ['polyglot.xhtml'], ['polyglot.shtml']];
    }

    #[DataProvider('dangerousNames')]
    public function test_backend_upload_rejects_image_with_dangerous_extension(string $name): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->post(route('backend.media.store'), ['file' => UploadedFile::fake()->image($name)])
            ->assertSessionHasErrors('file');

        $this->actingAs($editor)
            ->postJson(route('backend.media.ajax-upload'), ['file' => UploadedFile::fake()->image($name)])
            ->assertUnprocessable();

        $this->assertSame(0, Media::count());
    }

    public function test_uploaded_extension_is_normalised_to_lowercase(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.media.store'), ['file' => UploadedFile::fake()->image('PHOTO.JPG')])
            ->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.jpg', Media::first()->path);
    }

    public function test_api_upload_rejects_image_with_dangerous_extension(): void
    {
        $admin = $this->createAdmin();
        $token = ApiToken::createToken($admin, 'test')['plain_text'];

        $this->withToken($token)
            ->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('polyglot.html')])
            ->assertUnprocessable();

        $this->assertSame(0, Media::count());
    }
}
