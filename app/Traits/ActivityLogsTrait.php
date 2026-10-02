<?php

namespace App\Traits;


use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait ActivityLogsTrait
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $except = collect($this->logExcept);
        $alwaysIgnore = method_exists($this, 'getAlwaysIgnore') ? $this->getAlwaysIgnore() : ['created_at', 'updated_at'];

        $merged = $except->merge($alwaysIgnore);

        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept($merged->toArray());
    }


}
