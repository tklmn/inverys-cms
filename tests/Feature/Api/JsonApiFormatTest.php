<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\Page;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsonApiFormatTest extends TestCase
{
    use RefreshDatabase;

    private const JSON_API = 'application/vnd.api+json';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        $admin = $this->createAdmin();
        $this->token = ApiTokenFactory::createWithPlainText($admin)['plain_text'];
    }

    /** Headers that opt into JSON:API and authenticate. */
    private function jsonApiHeaders(): array
    {
        return [
            'Accept' => self::JSON_API,
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_collection_uses_json_api_resource_objects(): void
    {
        Page::factory()->count(3)->create();

        $response = $this->get('/api/v1/pages', $this->jsonApiHeaders());

        $response->assertOk()
            ->assertHeader('Content-Type', self::JSON_API)
            ->assertJsonPath('jsonapi.version', '1.1')
            ->assertJsonPath('data.0.type', 'pages')
            ->assertJsonCount(3, 'data');

        $first = $response->json('data.0');
        $this->assertArrayHasKey('id', $first);
        $this->assertIsString($first['id']);
        $this->assertArrayHasKey('attributes', $first);
        $this->assertArrayHasKey('title', $first['attributes']);
        // The id/type must not leak into attributes (JSON:API reserves those names).
        $this->assertArrayNotHasKey('id', $first['attributes']);
        $this->assertArrayNotHasKey('type', $first['attributes']);
    }

    public function test_single_resource_document(): void
    {
        $page = Page::factory()->create();

        $this->get("/api/v1/pages/{$page->id}", $this->jsonApiHeaders())
            ->assertOk()
            ->assertHeader('Content-Type', self::JSON_API)
            ->assertJsonPath('data.type', 'pages')
            ->assertJsonPath('data.id', (string) $page->id)
            ->assertJsonPath('data.attributes.title', $page->title);
    }

    public function test_reserved_type_attribute_is_renamed_for_media(): void
    {
        Media::factory()->image()->create();

        $response = $this->get('/api/v1/media', $this->jsonApiHeaders());

        $response->assertOk()->assertJsonPath('data.0.type', 'media');

        $attributes = $response->json('data.0.attributes');
        $this->assertArrayHasKey('media_type', $attributes);
        $this->assertArrayNotHasKey('type', $attributes);
    }

    public function test_non_standard_top_level_members_move_to_meta(): void
    {
        // ThemeController returns a bespoke {data, active} payload; `active` is not a
        // standard JSON:API top-level member and must be relocated under `meta`.
        $response = $this->get('/api/v1/themes', $this->jsonApiHeaders());

        $response->assertOk()
            ->assertJsonPath('jsonapi.version', '1.1')
            ->assertJsonStructure(['meta' => ['active'], 'data'])
            ->assertJsonMissingPath('active');

        $this->assertSame('themes', $response->json('data.0.type'));
    }

    public function test_validation_errors_are_json_api_error_objects(): void
    {
        $response = $this->post('/api/v1/pages', [], $this->jsonApiHeaders());

        $response->assertStatus(422)
            ->assertHeader('Content-Type', self::JSON_API)
            ->assertJsonStructure(['errors' => [['status', 'title', 'detail']]]);

        $errors = $response->json('errors');
        $this->assertSame('422', $errors[0]['status']);
        // The failing field is surfaced as an attribute source pointer.
        $pointers = array_column(array_column($errors, 'source'), 'pointer');
        $this->assertContains('/data/attributes/title', $pointers);
    }

    public function test_pagination_meta_is_preserved(): void
    {
        Page::factory()->count(3)->create();

        $this->get('/api/v1/pages', $this->jsonApiHeaders())
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_default_format_is_unchanged_without_opt_in(): void
    {
        $page = Page::factory()->create();

        // No Accept: application/vnd.api+json → default flat shape, no JSON:API envelope.
        $response = $this->getJson("/api/v1/pages/{$page->id}", ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJsonPath('data.title', $page->title)
            ->assertJsonMissingPath('data.attributes')
            ->assertJsonMissingPath('jsonapi');

        $this->assertStringNotContainsString(
            self::JSON_API,
            (string) $response->headers->get('Content-Type')
        );
    }
}
