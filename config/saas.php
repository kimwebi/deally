<?php

return [

    'switch_redirect' => env('SAAS_SWITCH_REDIRECT', 'deally.workspace'),

    'instances' => [
        'max_per_owner' => (int) env('SAAS_INSTANCES_MAX_PER_OWNER', 10),
        'owner_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('SAAS_INSTANCES_OWNER_ROLES', 'owner'))))),
    ],

];
