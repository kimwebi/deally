<?php

use Deally\Calls\CallsServiceProvider;
use Deally\Core\CoreServiceProvider;
use Deally\Pipeline\PipelineServiceProvider;
use Deally\Proposals\ProposalsServiceProvider;
use Deally\Settings\SettingsServiceProvider;
use Deally\Tasks\TasksServiceProvider;
use Deally\TenantManagement\TenantManagementServiceProvider;
use Deally\Workspace\WorkspaceServiceProvider;

return [
    CoreServiceProvider::class,
    CallsServiceProvider::class,
    PipelineServiceProvider::class,
    TasksServiceProvider::class,
    ProposalsServiceProvider::class,
    SettingsServiceProvider::class,
    WorkspaceServiceProvider::class,
    TenantManagementServiceProvider::class,
];
