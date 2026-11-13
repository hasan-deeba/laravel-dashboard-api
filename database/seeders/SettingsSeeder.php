<?php

namespace Database\Seeders;

use App\Models\General\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Safe defaults only: never overwrite an administrator's values.
        Setting::withoutEvents(function () {
            foreach (config('settings.fields', []) as $key => $definition) {
                Setting::firstOrCreate(['key' => $key], ['value' => $definition['default']]);
            }
        });
    }
}
