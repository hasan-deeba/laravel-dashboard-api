<?php

namespace Tests\Feature\Settings;

use App\Enum\GenderType;
use App\Enum\MaritalStatusType;
use App\Models\AccessManagement\Role;
use App\Models\AccessManagement\User;
use App\Models\Clients\Client;
use App\Models\Settings\Setting;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function editor(array $permissions = ['settings.view', 'settings.edit']): User
    {
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'cms');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'settings_manager', 'guard_name' => 'cms']);
        $role->syncPermissions($permissions);
        $user = User::firstOrCreate(['email' => 'settings@example.com'], [
            'name' => 'Settings manager',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function save(array $data, array $files = []): void
    {
        $result = app(SettingsService::class)->saveSettings($data, $files);
        $this->assertTrue($result->valid, $result->message);
    }

    protected function registrationPayload(): array
    {
        return [
            'first_name' => 'Hasan', 'last_name' => 'Deeba',
            'gender' => GenderType::MALE->value,
            'marital_status' => MaritalStatusType::SINGLE->value,
            'email' => 'client@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    public function test_public_settings_work_without_login_or_seeding(): void
    {
        $response = $this->getJson('/api/settings')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertJsonStructure(['message', 'data' => [
                'branding', 'support', 'legal', 'availability', 'apps', 'update',
            ]])
            ->assertJsonMissingPath('data.branding.app_name')
            ->assertJsonPath('data.legal.privacy_policy_text', null)
            ->assertJsonPath('data.legal.terms_text', null)
            ->assertJsonPath('data.branding.app_icon', null)
            ->assertJsonPath('data.apps.ios.lock_for_update', false)
            ->assertJsonPath('data.apps.android.lock_for_update', false)
            ->assertJsonPath('data.update', null)
            ->assertJsonMissingPath('data.code');
    }

    public function test_public_settings_return_only_markdown_in_the_requested_language(): void
    {
        $this->save(['legal' => [
            'privacy_policy_text' => ['en' => '# English privacy', 'ar' => '# سياسة الخصوصية'],
            'terms_text' => ['en' => '# English terms', 'ar' => '# الشروط'],
        ]]);

        $this->withHeader('Accept-Language', 'ar')->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.legal.privacy_policy_text', '# سياسة الخصوصية')
            ->assertJsonPath('data.legal.terms_text', '# الشروط')
            ->assertJsonMissing(['# English privacy', '# English terms'])
            ->assertJsonMissingPath('data.branding.app_name');

        $this->withHeader('Accept-Language', 'en')->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.legal.privacy_policy_text', '# English privacy')
            ->assertJsonPath('data.legal.terms_text', '# English terms')
            ->assertJsonMissing(['# سياسة الخصوصية', '# الشروط'])
            ->assertJsonMissingPath('data.branding.app_name');
    }

    public function test_cms_settings_require_authentication_and_view_permission(): void
    {
        $this->getJson('/cms/settings')->assertUnauthorized();
        $this->actingAs($this->editor([]), 'cms')->getJson('/cms/settings')->assertForbidden();
        $this->actingAs($this->editor(['settings.view']), 'cms')
            ->getJson('/cms/settings')->assertOk()->assertJsonPath('branding.app_name.en', 'HeroDash');
    }

    public function test_client_token_cannot_read_or_edit_cms_settings(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($client, 'clients')->getJson('/cms/settings')->assertUnauthorized();
        $this->actingAs($client, 'clients')->postJson('/cms/settings', [
            'branding' => ['app_name' => ['en' => 'Stolen']],
        ])->assertUnauthorized();
    }

    public function test_builder_has_typed_translated_fields_and_requires_permission(): void
    {
        $this->actingAs($this->editor([]), 'cms')->getJson('/cms/settings/builder')->assertForbidden();
        $response = $this->actingAs($this->editor(['settings.view']), 'cms')
            ->withHeader('Accept-Language', 'ar')
            ->getJson('/cms/settings/builder')
            ->assertOk()
            ->assertJsonPath('groups.0.label', 'الهوية البصرية')
            ->assertJsonPath('platforms.ios', 'iOS')
            ->assertJsonPath('types.IMAGE', 'صورة');

        $fields = collect($response->json('groups'))->flatMap(fn ($group) => $group['fields'])->keyBy('key');
        $this->assertCount(count(config('settings.fields')), $fields);
        $this->assertSame('أيقونة المشروع المشتركة لجميع المنصات', $fields['branding.app_icon']['label']);
        $this->assertSame('IMAGE', $fields['branding.app_icon']['type']);
        $this->assertSame(4096, $fields['branding.app_icon']['max_kilobytes']);
        $this->assertSame('MAJOR.MINOR.PATCH', $fields['apps.ios.version']['format']);
        $this->assertSame('TRANSLATABLE_MARKDOWN', $fields['legal.privacy_policy_text']['type']);
        $this->assertSame('markdown', $fields['legal.privacy_policy_text']['format']);
        $this->assertSame(50000, $fields['legal.terms_text']['max_length']);
        $this->assertTrue($fields['legal.terms_text']['nullable']);
        $this->assertArrayNotHasKey('branding.primary_color', $fields->all());
    }

    public function test_partial_update_preserves_types_and_unsubmitted_settings(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson('/cms/settings', [
            'branding' => ['app_name' => ['en' => 'Shared project', 'ar' => 'المشروع المشترك']],
            'support' => ['email' => 'help@example.com', 'phone' => '+31 (174) 123-456'],
            'legal' => ['privacy_policy_url' => 'https://example.com/privacy', 'terms_url' => 'https://example.com/terms'],
            'availability' => ['registration_enabled' => false],
        ])->assertOk()->assertJsonPath('message', 'Settings updated successfully.')->assertJsonPath('code', 200);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.branding.app_name.ar', 'المشروع المشترك')
            ->assertJsonPath('data.branding.logo', null)
            ->assertJsonPath('data.availability.registration_enabled', false);
        $this->assertSame(false, Setting::where('key', 'availability.registration_enabled')->first()->value);

        // A later translation-only patch must retain English and other groups.
        $this->actingAs($this->editor(), 'cms')->postJson('/cms/settings', [
            'branding' => ['app_name' => ['ar' => 'اسم جديد']],
        ])->assertOk();
        $this->assertSame(['en' => 'Shared project', 'ar' => 'اسم جديد'], app(SettingsService::class)->value('branding.app_name'));
        $this->assertSame('help@example.com', app(SettingsService::class)->value('support.email'));
    }

    public function test_unknown_keys_empty_payloads_and_invalid_values_are_rejected(): void
    {
        $user = $this->actingAs($this->editor(), 'cms');
        $user->postJson('/cms/settings', ['secret' => 'do-not-store'])->assertUnprocessable()->assertJsonValidationErrors('secret');
        $user->postJson('/cms/settings', [])->assertUnprocessable()->assertJsonValidationErrors('settings');
        $user->postJson('/cms/settings', ['support' => ['website_url' => 'not-a-url']])->assertUnprocessable();
        $user->postJson('/cms/settings', ['branding' => ['primary_color' => '#112233']])
            ->assertUnprocessable()->assertJsonValidationErrors('branding.primary_color');
        $user->postJson('/cms/settings', ['support' => ['email' => 'invalid']])->assertUnprocessable();
        $user->postJson('/cms/settings', ['localization' => ['default_locale' => 'ar']])
            ->assertUnprocessable()->assertJsonValidationErrors('localization.default_locale');
        $user->postJson('/cms/settings', ['availability' => ['maintenance_enabled' => 'perhaps']])->assertUnprocessable();
        $user->postJson('/cms/settings', ['apps' => ['ios' => ['version' => '1.2']]])->assertUnprocessable();
        $user->postJson('/cms/settings', ['branding' => ['app_name' => ['en' => 'Valid', 'fr' => 'Unknown']]])->assertUnprocessable();
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_legal_documents_offer_optional_links_and_markdown_text_per_language(): void
    {
        $user = $this->actingAs($this->editor(), 'cms');

        // Both the link and the text start empty; neither is required.
        $this->getJson('/api/settings')
            ->assertJsonPath('data.legal.privacy_policy_url', null)
            ->assertJsonPath('data.legal.privacy_policy_text', ['en' => null, 'ar' => null])
            ->assertJsonPath('data.legal.terms_text', ['en' => null, 'ar' => null]);

        // A link alone is valid.
        $user->postJson('/cms/settings', ['legal' => ['privacy_policy_url' => 'https://example.com/privacy']])
            ->assertOk();
        $this->getJson('/api/settings')
            ->assertJsonPath('data.legal.privacy_policy_url', 'https://example.com/privacy')
            ->assertJsonPath('data.legal.privacy_policy_text.en', null);

        // Markdown text alone is valid too, in both languages, alongside the link.
        $english = "# Privacy Policy\n\nWe **never** sell your data.\n\n- Point one\n- Point two\n\n[Contact](https://example.com)";
        $arabic = "# سياسة الخصوصية\n\nنحن **لا** نبيع بياناتك.\n\n- بند أول";
        $user->postJson('/cms/settings', ['legal' => [
            'privacy_policy_text' => ['en' => $english, 'ar' => $arabic],
            'terms_text' => ['en' => '# Terms'],
        ]])->assertOk();

        $this->getJson('/api/settings')
            ->assertJsonPath('data.legal.privacy_policy_text.en', $english)
            ->assertJsonPath('data.legal.privacy_policy_text.ar', $arabic)
            ->assertJsonPath('data.legal.terms_text.en', '# Terms')
            ->assertJsonPath('data.legal.terms_text.ar', null)
            ->assertJsonPath('data.legal.privacy_policy_url', 'https://example.com/privacy');

        // A single-language patch keeps the other language.
        $user->postJson('/cms/settings', ['legal' => ['privacy_policy_text' => ['ar' => 'نص محدث']]])->assertOk();
        $this->assertSame(['en' => $english, 'ar' => 'نص محدث'], app(SettingsService::class)->value('legal.privacy_policy_text'));

        // One language can be cleared on its own.
        $user->postJson('/cms/settings', ['legal' => ['privacy_policy_text' => ['ar' => null]]])->assertOk();
        $this->assertSame(['en' => $english, 'ar' => null], app(SettingsService::class)->value('legal.privacy_policy_text'));

        // Sending null clears the whole document without touching the link.
        $user->postJson('/cms/settings', ['legal' => ['privacy_policy_text' => null]])->assertOk();
        $this->getJson('/api/settings')
            ->assertJsonPath('data.legal.privacy_policy_text', ['en' => null, 'ar' => null])
            ->assertJsonPath('data.legal.privacy_policy_url', 'https://example.com/privacy');
    }

    public function test_legal_markdown_rejects_active_html_and_unknown_languages(): void
    {
        $user = $this->actingAs($this->editor(), 'cms');

        $user->postJson('/cms/settings', ['legal' => ['privacy_policy_text' => ['en' => 'Hi <script>alert(1)</script>']]])
            ->assertUnprocessable()->assertJsonValidationErrors('legal.privacy_policy_text.en');
        $user->postJson('/cms/settings', ['legal' => ['terms_text' => ['ar' => '<iframe src="https://evil.test"></iframe>']]])
            ->assertUnprocessable()->assertJsonValidationErrors('legal.terms_text.ar');
        $user->postJson('/cms/settings', ['legal' => ['terms_text' => ['fr' => 'Bonjour']]])
            ->assertUnprocessable()->assertJsonValidationErrors('legal.terms_text.fr');
        $user->postJson('/cms/settings', ['legal' => ['terms_text' => ['en' => str_repeat('a', 50001)]]])
            ->assertUnprocessable()->assertJsonValidationErrors('legal.terms_text.en');
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_viewer_cannot_update_settings(): void
    {
        $this->actingAs($this->editor(['settings.view']), 'cms')->postJson('/cms/settings', [
            'branding' => ['app_name' => ['en' => 'Not allowed']],
        ])->assertForbidden();
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_shared_icon_upload_url_replacement_and_removal(): void
    {
        // Some CI stream wrappers do not support LOCK_EX; production keeps the
        // disk's default locking. This only affects the isolated fake disk.
        Storage::fake('public', ['lock' => 0]);
        $user = $this->actingAs($this->editor(), 'cms');
        $user->post('/cms/settings', [
            'branding' => ['app_icon' => UploadedFile::fake()->image('icon.png', 512, 512)],
        ], ['Accept' => 'application/json'])->assertOk();

        $path = Setting::where('key', 'branding.app_icon')->first()->value;
        $this->assertStringStartsWith('settings/', $path);
        Storage::disk('public')->assertExists($path);
        $this->getJson('/api/settings')->assertJsonPath('data.branding.app_icon', url('storage/'.$path));
        $this->actingAs($this->editor(), 'cms')->getJson('/cms/settings')->assertJsonPath('branding.app_icon', url('storage/'.$path));

        $this->actingAs($this->editor(), 'cms')->post('/cms/settings', [
            'branding' => ['app_icon' => UploadedFile::fake()->image('replacement.webp', 256, 256)],
        ], ['Accept' => 'application/json'])->assertOk();
        $replacement = Setting::where('key', 'branding.app_icon')->first()->value;
        $this->assertNotSame($path, $replacement);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($replacement);

        $this->actingAs($this->editor(), 'cms')->postJson('/cms/settings', ['branding' => ['app_icon' => null]])->assertOk();
        Storage::disk('public')->assertMissing($replacement);
        $this->getJson('/api/settings')->assertJsonPath('data.branding.app_icon', null);
        $this->assertGreaterThan(0, Activity::where('subject_type', Setting::class)->count());
    }

    public function test_invalid_image_is_rejected_without_storage(): void
    {
        Storage::fake('public', ['lock' => 0]);
        $this->actingAs($this->editor(), 'cms')->post('/cms/settings', [
            'branding' => ['app_icon' => UploadedFile::fake()->create('not-an-image.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('branding.app_icon');
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_update_lock_requires_version_and_store_url_including_partial_updates(): void
    {
        $user = $this->actingAs($this->editor(), 'cms');
        $user->postJson('/cms/settings', ['apps' => ['ios' => ['lock_for_update' => true]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['apps.ios.version', 'apps.ios.store_url']);

        $user->postJson('/cms/settings', ['apps' => ['ios' => [
            'version' => '1.10.0', 'store_url' => 'https://apps.apple.com/app/example', 'lock_for_update' => true,
        ]]])->assertOk();
        $user->postJson('/cms/settings', ['apps' => ['ios' => ['version' => null]]])
            ->assertUnprocessable()->assertJsonValidationErrors('apps.ios.version');
        $user->postJson('/cms/settings', ['apps' => ['ios' => ['store_url' => null]]])
            ->assertUnprocessable()->assertJsonValidationErrors('apps.ios.store_url');
        $this->assertSame('1.10.0', app(SettingsService::class)->value('apps.ios.version'));
    }

    public function test_numeric_version_comparison_is_independent_per_platform(): void
    {
        $this->save(['apps' => [
            'ios' => ['version' => '1.10.0', 'lock_for_update' => true, 'store_url' => 'https://apps.apple.com/app/example'],
            'android' => ['version' => '2.0.0', 'lock_for_update' => true, 'store_url' => 'https://play.google.com/store/apps/details?id=example'],
        ]]);

        foreach ([
            ['ios', '1.9.0', true], ['ios', '1.10.0', false], ['ios', '1.10.1', false], ['ios', '2.0.0', false],
            ['android', '1.99.99', true], ['android', '2.0.0', false], ['android', '2.0.1', false],
        ] as [$platform, $version, $required]) {
            $this->getJson("/api/settings?platform={$platform}&app_version={$version}")
                ->assertOk()->assertJsonPath('data.update.required', $required)
                ->assertJsonPath('data.update.current_version', $version);
        }
        $this->getJson('/api/settings?platform=ios&app_version=1.0.0')
            ->assertJsonPath('data.update.store_url', 'https://apps.apple.com/app/example')
            ->assertJsonPath('data.update.message', 'Please update the app to continue.');
    }

    public function test_unlocked_platform_never_requires_an_update(): void
    {
        $this->save(['apps' => ['android' => ['version' => '9.0.0', 'lock_for_update' => false]]]);
        $this->getJson('/api/settings?platform=android&app_version=0.0.1')
            ->assertOk()->assertJsonPath('data.update.required', false);
    }

    public function test_version_context_must_be_complete_and_valid(): void
    {
        $this->getJson('/api/settings?platform=ios')->assertUnprocessable();
        $this->getJson('/api/settings?app_version=1.0.0')->assertUnprocessable();
        $this->getJson('/api/settings?platform=windows&app_version=1.0.0')->assertUnprocessable();
        $this->getJson('/api/settings?platform=ios&app_version=1.0')->assertUnprocessable();
        $this->getJson('/api/settings?platform=ios&app_version=1.0.0-beta')->assertUnprocessable();
    }

    public function test_update_message_uses_request_language_with_english_fallback(): void
    {
        $this->save(['apps' => ['ios' => [
            'version' => '2.0.0', 'lock_for_update' => true,
            'store_url' => 'https://apps.apple.com/app/example',
            'update_message' => ['en' => 'Update required', 'ar' => 'التحديث مطلوب'],
        ]]]);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/settings?platform=ios&app_version=1.0.0')
            ->assertJsonPath('data.update.message', 'التحديث مطلوب');
        $this->save(['apps' => ['ios' => ['update_message' => ['ar' => null]]]]);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/settings?platform=ios&app_version=1.0.0')
            ->assertJsonPath('data.update.message', 'Update required');
    }

    public function test_maintenance_blocks_client_routes_but_not_settings_or_cms(): void
    {
        $this->save(['availability' => [
            'maintenance_enabled' => true,
            'maintenance_message' => ['en' => 'Back soon', 'ar' => 'نعود قريباً'],
        ]]);
        $this->getJson('/api/settings')->assertOk()->assertJsonPath('data.availability.maintenance_enabled', true);
        $this->getJson('/api/register/builder')->assertStatus(503)
            ->assertExactJson(['message' => 'Back soon']);
        $this->postJson('/api/login', ['email' => 'client@example.com', 'password' => 'password123'])
            ->assertStatus(503)->assertExactJson(['message' => 'Back soon']);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/me')
            ->assertStatus(503)->assertExactJson(['message' => 'نعود قريباً']);
        $this->actingAs($this->editor(), 'cms')->getJson('/cms/settings')->assertOk();

        $this->save(['availability' => ['maintenance_enabled' => false]]);
        $this->getJson('/api/register/builder')->assertOk();
    }

    public function test_registration_switch_is_enforced_server_side_without_blocking_login(): void
    {
        $this->save(['availability' => ['registration_enabled' => false]]);
        $this->postJson('/api/register', $this->registrationPayload())
            ->assertForbidden()->assertExactJson(['message' => 'New registrations are currently disabled.']);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('users_otp_codes', 0);
        $this->getJson('/api/settings')->assertJsonPath('data.availability.registration_enabled', false);
        Client::factory()->create(['email' => 'existing@example.com', 'password' => 'password123']);
        $this->postJson('/api/login', ['email' => 'existing@example.com', 'password' => 'password123'])->assertOk();

        $this->save(['availability' => ['registration_enabled' => true]]);
        $this->postJson('/api/register', $this->registrationPayload())->assertCreated();
    }

    public function test_config_locale_is_default_and_supported_header_wins(): void
    {
        config(['app.locale' => 'ar']);
        $this->save([
            'availability' => [
                'maintenance_enabled' => true,
                'maintenance_message' => ['en' => 'Back soon', 'ar' => 'نعود قريباً'],
            ],
        ]);
        $this->assertSame('نعود قريباً', app(SettingsService::class)->value('availability.maintenance_message')['ar']);
        $this->withHeader('Accept-Language', '')->getJson('/api/register/builder')
            ->assertStatus(503)->assertExactJson(['message' => 'نعود قريباً']);
        $this->withHeader('Accept-Language', 'en')->getJson('/api/register/builder')
            ->assertStatus(503)->assertExactJson(['message' => 'Back soon']);
    }

    public function test_defaults_seeder_is_idempotent_and_does_not_overwrite_edits(): void
    {
        app(SettingsSeeder::class)->run();
        $this->assertDatabaseCount('settings', count(config('settings.fields')));
        $this->save(['branding' => ['app_name' => ['en' => 'Custom', 'ar' => 'مخصص']]]);
        app(SettingsSeeder::class)->run();
        $this->assertDatabaseCount('settings', count(config('settings.fields')));
        $this->assertSame('Custom', app(SettingsService::class)->value('branding.app_name')['en']);
    }
}
