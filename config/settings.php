<?php

use App\Enum\SettingType;

/*
|--------------------------------------------------------------------------
| Application settings registry
|--------------------------------------------------------------------------
| Only registered keys can be edited. "public" controls exposure to the
| unauthenticated web/mobile API. Never add passwords, API keys or tokens:
| secrets belong in the server environment, not in this database table.
|
| Add future settings here, add their labels, then run the defaults seeder.
| Image values are stored as relative paths on the public disk. Version
| strings use stable MAJOR.MINOR.PATCH numbers (native build IDs excluded).
*/

$settings = [
    'groups' => ['branding', 'localization', 'support', 'legal', 'availability', 'apps'],

    'fields' => [
        'branding.app_name' => [
            'type' => SettingType::TRANSLATABLE_TEXT,
            'default' => ['en' => 'HeroDash', 'ar' => 'HeroDash'],
            'nullable' => false, 'public' => true, 'max_length' => 100,
        ],
        'branding.app_icon' => [
            'type' => SettingType::IMAGE,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'branding.logo' => [
            'type' => SettingType::IMAGE,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'branding.favicon' => [
            'type' => SettingType::IMAGE,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'branding.primary_color' => [
            'type' => SettingType::COLOR,
            'default' => '#2563EB', 'nullable' => true, 'public' => true,
        ],
        'localization.default_locale' => [
            'type' => SettingType::LOCALE,
            'default' => 'en', 'nullable' => false, 'public' => true,
        ],
        'support.email' => [
            'type' => SettingType::EMAIL,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'support.phone' => [
            'type' => SettingType::PHONE,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'support.website_url' => [
            'type' => SettingType::URL,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'legal.privacy_policy_url' => [
            'type' => SettingType::URL,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'legal.terms_url' => [
            'type' => SettingType::URL,
            'default' => null, 'nullable' => true, 'public' => true,
        ],
        'availability.maintenance_enabled' => [
            'type' => SettingType::BOOLEAN,
            'default' => false, 'nullable' => false, 'public' => true,
        ],
        'availability.maintenance_message' => [
            'type' => SettingType::TRANSLATABLE_TEXT,
            'default' => [
                'en' => 'We are improving the app. Please try again shortly.',
                'ar' => 'نعمل على تحسين التطبيق. يرجى المحاولة مرة أخرى بعد قليل.',
            ],
            'nullable' => false, 'public' => true, 'max_length' => 2000,
        ],
        'availability.registration_enabled' => [
            'type' => SettingType::BOOLEAN,
            'default' => true, 'nullable' => false, 'public' => true,
        ],
    ],
];

// Each mobile platform has an independent release policy. When locked, the
// version is the minimum supported version; equal and newer versions pass.
$platformFields = [];
foreach (['ios', 'android'] as $platform) {
    $platformFields["apps.{$platform}.version"] = [
        'type' => SettingType::VERSION,
        'default' => null, 'nullable' => true, 'public' => true,
    ];
    $platformFields["apps.{$platform}.lock_for_update"] = [
        'type' => SettingType::BOOLEAN,
        'default' => false, 'nullable' => false, 'public' => true,
    ];
    $platformFields["apps.{$platform}.store_url"] = [
        'type' => SettingType::URL,
        'default' => null, 'nullable' => true, 'public' => true,
    ];
    $platformFields["apps.{$platform}.update_message"] = [
        'type' => SettingType::TRANSLATABLE_TEXT,
        'default' => [
            'en' => 'Please update the app to continue.',
            'ar' => 'يرجى تحديث التطبيق للمتابعة.',
        ],
        'nullable' => false, 'public' => true, 'max_length' => 2000,
    ];
}

$settings['fields'] = array_merge($settings['fields'], $platformFields);

return $settings;
