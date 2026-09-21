<?php

use App\Providers\AppServiceProvider;
use Deally\Calls\CallsServiceProvider;
use Deally\Core\CoreServiceProvider;
use Deally\Pipeline\PipelineServiceProvider;
use Deally\Proposals\ProposalsServiceProvider;
use Deally\Reporting\ReportingServiceProvider;
use Deally\Retention\RetentionServiceProvider;
use Deally\Settings\SettingsServiceProvider;
use Deally\Tasks\TasksServiceProvider;
use Deally\TenantManagement\TenantManagementServiceProvider;
use Deally\Workspace\WorkspaceServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CallsServiceProvider::class,
    PipelineServiceProvider::class,
    TasksServiceProvider::class,
    ProposalsServiceProvider::class,
    SettingsServiceProvider::class,
    WorkspaceServiceProvider::class,
    ReportingServiceProvider::class,
    RetentionServiceProvider::class,
    TenantManagementServiceProvider::class,
];
