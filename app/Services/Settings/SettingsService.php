<?php

namespace App\Services\Settings;

use App\Enum\AppPlatform;
use App\Enum\SettingType;
use App\Models\Settings\Setting;
use App\Services\Cms\CrudService;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared by the dashboard and the public web/mobile API. This is a settings
 * collection, not an arbitrary-key CRUD resource: only registry keys are saved.
 */
class SettingsService extends CrudService
{
    public function __construct()
    {
        parent::__construct(Setting::class);
    }

    public function index(Request $request): ResultService
    {
        return new ResultService(message: __('messages.Success'), item: $this->values());
    }

    public function builder(Request $request): ResultService
    {
        $groups = [];
        foreach (config('settings.groups', []) as $group) {
            $groups[$group] = [
                'key' => $group,
                'label' => __("settings.groups.{$group}"),
                'fields' => [],
            ];
        }

        foreach ($this->definitions() as $key => $definition) {
            $field = [
                'key' => $key,
                'type' => $definition['type']->value,
                'label' => __('settings.fields.'.str_replace('.', '_', $key)),
                'is_public' => $definition['public'],
                'nullable' => $definition['nullable'],
                'default' => $definition['default'],
            ];

            if (isset($definition['max_length'])) {
                $field['max_length'] = $definition['max_length'];
            }
            if ($definition['type'] === SettingType::LOCALE) {
                $field['options'] = ['en' => 'English', 'ar' => 'العربية'];
            }
            if ($definition['type'] === SettingType::IMAGE) {
                $field['accept'] = ['jpg', 'jpeg', 'png', 'webp'];
                $field['max_kilobytes'] = 4096;
            }
            if ($definition['type'] === SettingType::VERSION) {
                $field['format'] = 'MAJOR.MINOR.PATCH';
            }

            $groups[explode('.', $key)[0]]['fields'][] = $field;
        }

        return new ResultService(message: __('messages.Success'), item: [
            'groups' => array_values($groups),
            'types' => SettingType::labels(),
            'platforms' => AppPlatform::labels(),
        ]);
    }

    public function saveSettings(array $data, array $files = []): ResultService
    {
        $newFiles = [];
        $oldFiles = [];
        $files = Arr::dot($files);
        $current = $this->effectiveValues();

        try {
            $result = DB::transaction(function () use ($data, $files, $current, &$newFiles, &$oldFiles) {
                foreach ($this->definitions() as $key => $definition) {
                    if (!Arr::has($data, $key) && !Arr::has($files, $key)) {
                        continue;
                    }

                    $value = Arr::get($files, $key, Arr::get($data, $key));
                    $value = match ($definition['type']) {
                        SettingType::BOOLEAN => (bool) $value,
                        SettingType::TRANSLATABLE_TEXT => array_replace(
                            $current[$key],
                            Arr::only($value, ['en', 'ar']),
                        ),
                        SettingType::IMAGE => $value instanceof UploadedFile
                            ? $this->storeImage($value, $newFiles)
                            : null,
                        default => $value,
                    };

                    $setting = Setting::firstOrNew(['key' => $key]);
                    $oldValue = $setting->value;
                    if ($setting->exists && $oldValue === $value) {
                        continue;
                    }

                    if ($definition['type'] === SettingType::IMAGE && $this->isManagedImagePath($oldValue)) {
                        $oldFiles[] = $oldValue;
                    }

                    $setting->value = $value;
                    $setting->save();
                }

                return new ResultService(message: __('messages.Settings updated successfully'));
            });

            // Never unlink a still-used image before the database commits.
            foreach (array_unique($oldFiles) as $path) {
                if (in_array($path, $newFiles, true)) {
                    continue;
                }
                try {
                    Storage::disk('public')->delete($path);
                } catch (Throwable $e) {
                    report($e); // An orphaned old file must not undo saved settings.
                }
            }

            return $result;
        } catch (Throwable $e) {
            foreach ($newFiles as $path) {
                try {
                    Storage::disk('public')->delete($path);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            report($e);

            return new ResultService(false, 500, __('messages.Unable to save settings'));
        }
    }

    public function publicSettings(array $query = []): ResultService
    {
        $values = $this->values(publicOnly: true);
        $update = null;

        if (isset($query['platform'], $query['app_version'])) {
            $policy = $values['apps'][$query['platform']];
            $required = $policy['lock_for_update']
                && $policy['version'] !== null
                && version_compare($query['app_version'], $policy['version'], '<');

            $update = [
                'required' => $required,
                'current_version' => $query['app_version'],
                'required_version' => $policy['version'],
                'store_url' => $policy['store_url'],
                'message' => $this->localized($policy['update_message']),
            ];
        }

        return new ResultService(
            message: __('messages.Success'),
            item: array_replace($values, ['update' => $update]),
        );
    }

    public function clientAvailability(): ResultService
    {
        $values = $this->effectiveValues();

        if ($values['availability.maintenance_enabled']) {
            return new ResultService(false, 503, $this->localized($values['availability.maintenance_message']));
        }

        return new ResultService();
    }

    public function registrationAvailability(): ResultService
    {
        if (!$this->effectiveValues()['availability.registration_enabled']) {
            return new ResultService(false, 403, __('messages.Registration is disabled'));
        }

        return new ResultService();
    }

    /** Read a registered setting, falling back to its safe default. */
    public function value(string $key): mixed
    {
        return $this->effectiveValues()[$key] ?? null;
    }

    protected function definitions(): array
    {
        return config('settings.fields', []);
    }

    protected function storedValues(): array
    {
        // Read fresh each time: lock/maintenance/registration changes must be
        // visible immediately, including to long-lived route/controller instances.
        return Setting::pluck('value', 'key')->all();
    }

    protected function effectiveValues(): array
    {
        $defaults = [];
        foreach ($this->definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        // Missing rows use defaults; an explicitly saved null stays null.
        return array_replace($defaults, Arr::only($this->storedValues(), array_keys($defaults)));
    }

    protected function values(bool $publicOnly = false): array
    {
        $values = [];
        foreach ($this->effectiveValues() as $key => $value) {
            $definition = $this->definitions()[$key];
            if ($publicOnly && !$definition['public']) {
                continue;
            }
            if ($definition['type'] === SettingType::IMAGE && $value !== null) {
                $value = url('storage/'.$value);
            }
            Arr::set($values, $key, $value);
        }

        return $values;
    }

    protected function localized(array $translations): string
    {
        return $translations[app()->getLocale()] ?? $translations['en'] ?? '';
    }

    protected function storeImage(UploadedFile $file, array &$newFiles): string
    {
        $path = Storage::disk('public')->putFile('settings', $file);
        if ($path === false) {
            throw new \RuntimeException('Unable to store the settings image.');
        }

        $newFiles[] = $path;

        return $path;
    }

    protected function isManagedImagePath(mixed $path): bool
    {
        return is_string($path) && str_starts_with($path, 'settings/') && !str_contains($path, '..');
    }
}
