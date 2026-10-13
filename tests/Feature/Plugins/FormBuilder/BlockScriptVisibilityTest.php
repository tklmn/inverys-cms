<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Http\Middleware\InjectPluginHooks;
use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Tests\TestCase;

/**
 * The page-builder script of the form builder lists every active form. Head
 * hooks run on the website as well, so the list must stay in the backend.
 */
class BlockScriptVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        $plugin = Plugin::create([
            'slug' => 'form-builder',
            'name' => 'Form Builder',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);

        $manager = app(PluginManager::class);
        $manager->discover();
        $provider = new FormBuilderServiceProvider(app(), $plugin, $manager->get('form-builder'));
        $provider->register();
        $provider->boot();
        $provider->install();

        Form::create([
            'name' => 'Internal HR complaint',
            'slug' => 'internal-hr',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);
    }

    private function render(string $path): string
    {
        $request = Request::create($path);
        $this->app->instance('request', $request);

        return view('form-builder-admin::block-type-script')->render();
    }

    public function test_website_pages_do_not_list_the_forms(): void
    {
        $request = Request::create('/');
        $this->app->instance('request', $request);

        $html = (new InjectPluginHooks)->handle($request, fn () => new Response('<html><head></head><body></body></html>', 200, ['Content-Type' => 'text/html']))->getContent();

        $this->assertStringNotContainsString('Internal HR complaint', $html);
        $this->assertSame('', trim($this->render('/about-us')));
    }

    public function test_page_builder_in_the_backend_still_gets_the_list(): void
    {
        $this->assertStringContainsString('Internal HR complaint', $this->render('/backend/pages/1/edit'));
    }
}
