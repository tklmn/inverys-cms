<?php

namespace Tests\Feature\Security;

use App\Models\ApiToken;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Plugins\ActivityLog\Models\ActivityLog;
use Plugins\FormBuilder\Mail\FormSubmissionNotification;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Plugins\Gallery\Models\GalleryAlbum;
use Tests\TestCase;

/**
 * Permission and output gaps in the bundled plugins.
 */
class PluginSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    protected function tearDown(): void
    {
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    private function enablePlugins(string ...$slugs): void
    {
        $manager = app(PluginManager::class);
        $manager->discover();

        foreach ($slugs as $slug) {
            Plugin::create(['slug' => $slug, 'name' => $slug, 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
            $manager->registerOnly($slug);
            $manager->getLoadedProviders()[$slug]->install();
        }

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->permissions()->sync(collect($permissions)->map(fn ($p) => Permission::firstOrCreate(['name' => $p])->id));

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_cookie_consent_requires_manage_plugins(): void
    {
        $this->enablePlugins('cookie-consent');
        $editor = $this->createEditor();

        $this->actingAs($editor)->get(route('backend.cookie-consent.services'))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.cookie-consent.settings'))->assertForbidden();
        $this->actingAs($editor)->post(route('backend.cookie-consent.services.store'), [
            'name' => 'Evil', 'category' => 'functional', 'position' => 'head',
            'script' => '<meta http-equiv="refresh" content="0;url=https://evil.example">',
        ])->assertForbidden();

        $this->assertStringNotContainsString('evil.example', (string) Setting::where('key', 'cc.services')->value('value'));
        $this->actingAs($this->createAdmin())->get(route('backend.cookie-consent.services'))->assertOk();
    }

    public function test_cookie_consent_scripts_are_not_rendered_in_the_backend(): void
    {
        $this->enablePlugins('cookie-consent');
        Setting::updateOrCreate(['key' => 'cc.services'], ['value' => json_encode([[
            'id' => 'x', 'name' => 'X', 'category' => 'statistics', 'position' => 'head',
            'script' => '<!-- tracking-marker -->',
        ]]), 'type' => 'plugin_setting']);
        EloquentSettingRepository::clearCache();

        $this->actingAs($this->createAdmin())
            ->withUnencryptedCookie('cc_consent', json_encode(['ts' => time(), 'statistics' => true]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('tracking-marker', false);
    }

    public function test_mail_provider_oauth_requires_settings_permission(): void
    {
        $this->enablePlugins('mail-providers');
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->post(route('backend.mail-providers.oauth.authorize', ['provider' => 'gmail']))
            ->assertForbidden();
        $this->actingAs($editor)
            ->post(route('backend.mail-providers.oauth.disconnect', ['provider' => 'gmail']))
            ->assertForbidden();
        $this->actingAs($editor)
            ->get(route('mail-providers.oauth.callback', ['provider' => 'gmail', 'code' => 'x', 'state' => 'y']))
            ->assertForbidden();
    }

    public function test_gallery_caption_update_requires_edit_permission(): void
    {
        $this->enablePlugins('gallery');
        Storage::fake('public');
        $album = GalleryAlbum::create(['name' => 'Album', 'slug' => 'album', 'is_active' => true]);
        $media = Media::factory()->create();
        $album->images()->attach($media->id, ['caption' => 'orig', 'sort_order' => 0]);

        $viewer = $this->userWith(['access-dashboard', 'show-gallery-album']);

        $this->actingAs($viewer)
            ->putJson(route('backend.gallery.images.caption', ['album' => $album->id, 'image' => $media->id]), ['caption' => '<a href=//evil>x</a>'])
            ->assertForbidden();

        $this->assertSame('orig', $album->images()->first()->pivot->caption);
    }

    public function test_activity_log_masks_secret_settings(): void
    {
        $this->enablePlugins('activity-log');
        $this->actingAs($this->createAdmin());

        $setting = Setting::create(['key' => 'captcha.hcaptcha_secret_key', 'value' => 'very-secret-value', 'type' => 'string']);
        $setting->update(['value' => 'rotated-secret-value']);

        $logged = ActivityLog::where('subject_type', Setting::class)->get()->map(fn ($l) => json_encode($l->properties))->implode(' ');

        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString('very-secret-value', $logged);
        $this->assertStringNotContainsString('rotated-secret-value', $logged);
    }

    public function test_sender_copy_carries_no_attachments(): void
    {
        $this->enablePlugins('form-builder');
        Storage::fake('local');
        Storage::disk('local')->put('form-submissions/contact/file.pdf', 'pdf');

        $form = Form::create(['name' => 'Contact', 'slug' => 'contact', 'fields' => [], 'settings' => [], 'is_active' => true]);
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['email' => 'victim@example.com'],
            'files' => ['upload' => 'form-submissions/contact/file.pdf'],
        ]);

        $this->assertCount(1, (new FormSubmissionNotification($form, $submission))->attachments());
        $this->assertSame([], (new FormSubmissionNotification($form, $submission, true))->attachments());
    }

    public function test_blog_prefix_cannot_shadow_core_routes(): void
    {
        $this->enablePlugins('blog');
        $admin = $this->userWith(['access-dashboard', 'blog-settings']);

        $this->actingAs($admin)
            ->put(route('backend.blog.settings.update'), [
                'url_prefix' => 'backend',
                'posts_per_page' => 10,
                'columns' => 1,
                'sort_by' => 'published_at',
                'sort_direction' => 'desc',
            ])
            ->assertSessionHasErrors('url_prefix');
    }

    public function test_blog_api_publish_requires_publish_permission(): void
    {
        $this->enablePlugins('blog');
        $author = $this->userWith(['api-access', 'show-post', 'create-post', 'edit-post']);
        $token = ApiToken::createToken($author, 'test')['plain_text'];

        $this->withToken($token)
            ->postJson('/api/v1/blog/posts', ['title' => 'Sneaky', 'is_active' => true])
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/v1/blog/posts', ['title' => 'Draft', 'is_active' => false])
            ->assertCreated();
    }
}
