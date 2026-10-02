<?php

namespace App\Models\AccessManagement;

use App\Enum\ActivityLogType;
use App\Helpers\AppHelper;
use App\Traits\ModelTrait;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    use ModelTrait;

    protected $minimumAllowedKey = ['id', 'log_name', 'subject_type', 'subject_id', 'causer', 'description', 'properties',
        'event_type', 'batch_uuid', 'created_at', 'changes'];
    protected $notAllowedKey = ['updated_at'];
    protected $specialFields = [];
    protected $extraFields = [];

    public function __construct(array $attributes = array())
    {
        $this->extraFields = [
            'changes' => function () {
                return $this->formatChanges();
            },
            'causer' => function () {
                return $this->causer?->transformItemInclude() ?? null;
            },
            'event_type' => function () {
                return [
                    'event' => ActivityLogType::label($this->event),
                    'color' => ActivityLogType::color($this->event)
                ];
            },
        ];
        $this->specialFields = [
            'subject_type' => function () {
                return $this->formatSubjectType();
            },
            'created_at' => function () {
                return AppHelper::humanDate($this->created_at);
            }
        ];
        parent::__construct($attributes);
    }

    protected function formatChanges(): array
    {
        $properties = $this->properties ?? [];
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        // 1. UPDATED event: Build a diff array comparing old vs new
        if ($this->description === 'updated' || (!empty($old) && !empty($attributes))) {
            $keys = array_unique(array_merge(array_keys($attributes), array_keys($old)));
            $diffs = [];

            foreach ($keys as $key) {
                $oldVal = $old[$key] ?? null;
                $newVal = $attributes[$key] ?? null;

                // Only include fields that actually changed
                if ($oldVal !== $newVal) {
                    $diffs[] = [
                        'field' => __('activityLogs.' . $key),
                        'old_value' => is_array($oldVal) ? json_encode($oldVal) : (string) ($oldVal ?? '—'),
                        'new_value' => is_array($newVal) ? json_encode($newVal) : (string) ($newVal ?? '—'),
                    ];
                }
            }

            return [
                'type' => 'diff',
                'items' => $diffs,
            ];
        }

        // 2. CREATED / DELETED / CUSTOM events: Return flat key-value array
        $sourceData = !empty($attributes) ? $attributes : $old;
        $formatted = [];

        foreach ($sourceData as $key => $value) {
            $formatted[] = [
                'field' => __('activityLogs.' . $key),
                'value' => is_array($value) ? json_encode($value) : (string) ($value ?? '—'),
            ];
        }

        return [
            'type' => 'single',
            'items' => $formatted,
        ];
    }

    protected function formatSubjectType(): string
    {
        if (!$this->subject_type) {
            return '—';
        }

        $className = class_basename($this->subject_type);

        $headline = Str::headline($className);

        $translationKey = "activityLogs.{$className}";

        if (__($translationKey) !== $translationKey) {
            return __($translationKey);
        }

        return __($headline);
    }
}
