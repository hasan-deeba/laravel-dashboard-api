<?php

namespace App\Helpers;



use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class AppHelper
{
    public static function languages(): array
    {
        return ['ar', 'en'];
    }
    public static function localizeFields(string $baseField): string
    {
        $locale = App::getLocale();
        return $baseField . '_' . $locale;
    }
    public static function humanDate($date): string
    {
        $locale = app()->getLocale();
        Carbon::setLocale($locale);

        $hasExplicitTime = preg_match('/\d{2}:\d{2}/', $date);

        return Carbon::parse($date)->translatedFormat($hasExplicitTime ? 'l، j F Y \a\t g:i A' : 'l، j F Y');
    }

    public static function getSettingValue(string $key): mixed
    {
        return app(SettingsService::class)->value($key);
    }



}
