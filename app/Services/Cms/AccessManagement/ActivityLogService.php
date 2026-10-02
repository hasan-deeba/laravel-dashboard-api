<?php

namespace App\Services\Cms\AccessManagement;

use App\Models\AccessManagement\ActivityLog;
use App\Services\Cms\CrudService;

class ActivityLogService extends CrudService
{
    public function __construct()
    {
        parent::__construct(ActivityLog::class);

        $tableName = config('activitylog.table_name', 'activity_log');

        $this->searchColumns(
            normal: ['log_name', 'subject_type', 'subject_id', 'causer_id'],
            advanced: ['log_name', 'subject_type', 'subject_id', 'causer_id', 'created_at', 'updated_at']
        )
            ->orderBy(['id'], 'desc')
            ->report(
                headings: ['#ID', 'Log Name', 'Subject', 'Causer', 'Date'],
                fields: [
                    "{$tableName}.id",
                    "{$tableName}.log_name",
                    "{$tableName}.subject",
                    "{$tableName}.causer",
                    "{$tableName}.created_at",
                ]
            );
    }
}
