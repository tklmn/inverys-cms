<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Precedence: the user's own choice in the profile beats the site default.
 * "Site default" (empty) removes the own choice — the profile then follows the
 * site again, even if the admin changes the default later.
 */
class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        Setting::create(['key' => 'timezone', 'value' => 'Europe/Berlin']);
        Setting::create(['key' => 'date_format', 'value' => 'd.m.Y']);
        Setting::create(['key' => 'language', 'value' => 'en']);
    }

    private function profilePayload(User $user, array $settings): array
    {
        return array_merge([
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ], $settings);
    }

    public function test_saving_the_profile_does_not_copy_site_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', $this->profilePayload($user, ['language' => '', 'timezone' => '', 'date_format' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, UserSetting::where('user_id', $user->id)->whereIn('key', ['language', 'timezone', 'date_format'])->count());

        // The admin changes the default — the user follows
        Setting::where('key', 'timezone')->update(['value' => 'America/New_York']);
        $this->assertSame('America/New_York', $user->fresh()->getSetting('timezone'));
    }

    public function test_own_choice_overrides_and_site_default_removes_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', $this->profilePayload($user, ['timezone' => 'Asia/Tokyo', 'date_format' => 'Y-m-d']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Asia/Tokyo', $user->fresh()->getSetting('timezone'));
        $this->assertSame('Y-m-d', $user->fresh()->getSetting('date_format'));

        $this->actingAs($user)
            ->patch('/profile', $this->profilePayload($user, ['timezone' => '', 'date_format' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Europe/Berlin', $user->fresh()->getSetting('timezone'));
        $this->assertSame('d.m.Y', $user->fresh()->getSetting('date_format'));
    }

    public function test_profile_validates_language_and_timezone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', $this->profilePayload($user, ['language' => 'xx', 'timezone' => 'Mars/Olympus']))
            ->assertSessionHasErrors(['language', 'timezone']);
    }

    public function test_profile_offers_the_site_default_and_shows_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee(__('translation.users.site_default', ['value' => 'Europe/Berlin']))
            ->assertSee(__('translation.users.site_default', ['value' => 'English']))
            ->assertSee(__('translation.users.settings_hint'));
    }

    public function test_appearance_can_follow_the_site_default_again(): void
    {
        $user = User::factory()->create();
        Setting::create(['key' => 'theme', 'value' => 'dark']);

        $this->actingAs($user)->post(route('profile.theme.update'), ['theme' => 'light']);
        $this->assertSame('light', $user->fresh()->getSetting('theme'));

        $this->actingAs($user)->post(route('profile.theme.update'), ['theme' => '']);
        $this->assertSame('dark', $user->fresh()->getSetting('theme'));
        $this->assertFalse(UserSetting::where('user_id', $user->id)->where('key', 'theme')->exists());
    }

    public function test_empty_own_date_format_falls_back_to_the_site_format(): void
    {
        $user = User::factory()->create();
        // An empty stored field
        UserSetting::create(['user_id' => $user->id, 'key' => 'date_format', 'value' => '']);

        $this->actingAs($user);

        $this->assertSame('24.09.2026', format_date(Carbon::parse('2026-09-24 12:00:00')));
    }

    public function test_own_time_zone_changes_only_the_display(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'key' => 'timezone', 'value' => 'Asia/Tokyo']);

        $this->actingAs($user);

        // 12:00 Berlin (summer time) = 19:00 Tokyo
        $stored = Carbon::parse('2026-09-24 12:00:00', 'Europe/Berlin');
        $this->assertStringContainsString('19:00', format_datetime($stored));
        $this->assertSame('Europe/Berlin', $stored->timezoneName);
    }
}
