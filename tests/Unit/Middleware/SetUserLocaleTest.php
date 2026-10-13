<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetUserLocale;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SetUserLocaleTest extends TestCase
{
    use RefreshDatabase;

    private SetUserLocale $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SetUserLocale;

        // Only installed locales are accepted — register the ones used below
        // like language packs would.
        $locales = app(Locales::class);
        $locales->register('de', 'German', 'Deutsch');
        $locales->register('fr', 'French', 'Français');
        $locales->register('es', 'Spanish', 'Español');
    }

    public function test_locale_that_is_not_installed_falls_back_to_english(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'key' => 'language', 'value' => 'it']);
        Setting::create(['key' => 'language', 'value' => 'nl']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $this->actingAs($user);

        $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals('en', App::getLocale());
    }

    public function test_visitor_locale_wins_over_user_setting(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'key' => 'language', 'value' => 'de']);
        app(Locales::class)->setVisitor('fr');

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $this->actingAs($user);

        $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals('fr', App::getLocale());
    }

    public function test_guest_keeps_the_last_visited_language_on_pages_without_prefix(): void
    {
        // /de/… visited: the language comes from the prefix and is remembered
        $session = app('session')->driver();
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($session);
        app(Locales::class)->setVisitor('de');

        $this->middleware->handle($request, fn () => new Response('OK'));
        $this->assertEquals('de', $session->get('visitor_locale'));

        // /login has no prefix — form and error messages stay German
        app(Locales::class)->setVisitor(null);
        App::setLocale('en');
        $login = Request::create('/login', 'POST');
        $login->setLaravelSession($session);

        $this->middleware->handle($login, fn () => new Response('OK'));

        $this->assertEquals('de', App::getLocale());
    }

    public function test_authenticated_user_with_language_setting_sets_locale(): void
    {
        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'de',
        ]);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals('de', App::getLocale());
    }

    public function test_falls_back_to_global_setting(): void
    {
        $user = User::factory()->create();
        Setting::create(['key' => 'language', 'value' => 'fr']);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals('fr', App::getLocale());
    }

    public function test_defaults_to_app_locale_when_no_setting_exists(): void
    {
        $originalLocale = App::getLocale();
        $user = User::factory()->create();

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals($originalLocale, App::getLocale());
    }

    public function test_guest_uses_global_setting(): void
    {
        Setting::create(['key' => 'language', 'value' => 'es']);

        $request = Request::create('/test', 'GET');

        $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals('es', App::getLocale());
    }

    public function test_user_setting_takes_precedence_over_global(): void
    {
        $user = User::factory()->create();
        Setting::create(['key' => 'language', 'value' => 'fr']);
        UserSetting::create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'de',
        ]);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals('de', App::getLocale());
    }
}
