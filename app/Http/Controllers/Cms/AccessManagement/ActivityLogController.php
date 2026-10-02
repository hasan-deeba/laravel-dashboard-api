<?php

namespace App\Http\Controllers\Cms\AccessManagement;

use App\Http\Controllers\CrudController;
use App\Services\Cms\AccessManagement\ActivityLogService;

class ActivityLogController extends CrudController
{
    protected string $permissionPrefix = 'activity_logs';

    public function __construct(ActivityLogService $activityLogService)
    {
        parent::__construct($activityLogService);
    }
}
