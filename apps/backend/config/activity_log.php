<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit types (security / administration)
    |--------------------------------------------------------------------------
    */
    'audit_types' => [
        'login',
        'user',
        'role',
        'config',
        'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity types (operational CRUD)
    |--------------------------------------------------------------------------
    */
    'activity_types' => [
        'customer',
        'ticket',
        'complaint',
        'activation',
        'cctv',
        'dismantle',
        'noc',
        'router',
        'network',
        'backup',
        'report',
    ],
];
