<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Rules\WebVttFile;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * WebVTT caption files (video block captions, WCAG 1.2.2) in the media
 * library: accepted by content, stored as text/vtt, never treated as image.
 */
class MediaCaptionUploadTest extends TestCase
{
    use RefreshDatabase;

    private const VTT = "WEBVTT\n\n00:00:00.000 --> 00:00:02.500\nWelcome to <b>Inverys</b>.\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
    }

    private function vtt(string $content = self::VTT, string $name = 'captions-en.vtt'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_a_caption_file_can_be_uploaded(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.media.store'), ['file' => $this->vtt()])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $media = Media::firstOrFail();
        $this->assertSame('text/vtt', $media->mime_type);
        $this->assertStringEndsWith('.vtt', $media->path);
        $this->assertNull($media->thumbnail_path);
        $this->assertSame('file', $media->type);
        $this->assertFalse($media->needsAltText());
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_a_byte_order_mark_is_fine(): void
    {
        $this->actingAs($this->createAdmin())
            ->postJson(route('backend.media.ajax-upload'), ['file' => $this->vtt("\xEF\xBB\xBF".self::VTT)])
            ->assertOk()
            ->assertJsonPath('type', 'text/vtt');
    }

    public function test_files_that_are_not_webvtt_are_refused(): void
    {
        $admin = $this->createAdmin();
        $refused = [
            'no signature' => "00:00:00.000 --> 00:00:02.500\nHello\n",
            'signature not at the start' => "\nWEBVTT\n",
            'signature glued to text' => "WEBVTTX\n",
            'html document' => "WEBVTT\n\n<html><body><script>alert(1)</script></body></html>",
            'script in a cue' => "WEBVTT\n\n00:00:00.000 --> 00:00:01.000\n<script>alert(1)</script>\n",
            'svg' => "WEBVTT\n<svg onload=alert(1)>",
            'binary' => "WEBVTT\n\0\0\x89PNG",
            'not utf-8' => "WEBVTT\n\xC3\x28",
        ];

        foreach ($refused as $case => $content) {
            $this->actingAs($admin)
                ->post(route('backend.media.store'), ['file' => $this->vtt($content)])
                ->assertSessionHasErrors('file', "Case: {$case}");
        }

        $this->assertSame(0, Media::count());
    }

    public function test_a_png_named_vtt_is_refused(): void
    {
        $png = UploadedFile::fake()->image('captions.png', 8, 8);
        $disguised = UploadedFile::fake()->createWithContent('captions.vtt', file_get_contents($png->getRealPath()));

        $this->actingAs($this->createAdmin())
            ->post(route('backend.media.store'), ['file' => $disguised])
            ->assertSessionHasErrors('file');
    }

    public function test_other_text_files_are_still_refused(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.media.store'), ['file' => UploadedFile::fake()->createWithContent('captions.txt', self::VTT)])
            ->assertSessionHasErrors('file');
    }

    public function test_caption_files_are_listed_as_files_not_images(): void
    {
        $this->actingAs($this->createAdmin())->post(route('backend.media.store'), ['file' => $this->vtt(name: 'qqsubtitlesqq.vtt')]);

        $admin = $this->createAdmin();
        $this->actingAs($admin)->getJson(route('backend.media.api.list', ['type' => 'image']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($admin)->getJson(route('backend.media.api.list', ['type' => 'captions']))
            ->assertOk()
            ->assertJsonPath('data.0.mime_type', 'text/vtt');

        $this->actingAs($admin)->get(route('backend.media.index', ['type' => 'captions']))
            ->assertOk()
            ->assertSee('qqsubtitlesqq');
    }

    public function test_the_api_accepts_caption_files(): void
    {
        $token = ApiTokenFactory::createWithPlainText($this->createAdmin())['plain_text'];

        $this->post('/api/v1/media', ['file' => $this->vtt()], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame('text/vtt', Media::firstOrFail()->mime_type);

        $this->post('/api/v1/media', ['file' => $this->vtt('<html>')], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_signature_check(): void
    {
        $this->assertTrue(WebVttFile::looksLikeWebVtt('WEBVTT'));
        $this->assertTrue(WebVttFile::looksLikeWebVtt("WEBVTT - Captions\r\n"));
        $this->assertTrue(WebVttFile::looksLikeWebVtt("WEBVTT\tKind: captions\n"));
        $this->assertFalse(WebVttFile::looksLikeWebVtt('webvtt'));
        $this->assertFalse(WebVttFile::looksLikeWebVtt(''));
    }
}
