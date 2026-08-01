<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permission catalog (grouped for Role UI)
    |--------------------------------------------------------------------------
    */
    'groups' => [
        [
            'key' => 'modules',
            'label' => 'Modul / Menu',
            'permissions' => [
                'dashboard.view' => 'Dashboard',
                'monitoring.view' => 'Monitoring',
                'complaint.view' => 'Komplain',
                'activation.view' => 'Aktivasi / CCTV / Update NOC',
                'dismantle.view' => 'Dismantle',
                'ticket.view' => 'Ticket (lihat)',
                'ticket.manage' => 'Ticket (kelola)',
                'customer.view' => 'Pelanggan (lihat)',
                'customer.manage' => 'Pelanggan (kelola)',
                'package.view' => 'Paket Internet',
                'network.view' => 'Jaringan (lihat)',
                'network.manage' => 'Jaringan (kelola)',
                'report.view' => 'Laporan (lihat)',
                'report.generate' => 'Generate Report',
                'analytics.view' => 'Analytics',
                'master.view' => 'Master Data',
                'user.view' => 'Kelola User (lihat)',
                'user.manage' => 'Kelola User (kelola)',
                'role.manage' => 'Role & Permission',
                'audit.view' => 'Audit / Activity Log',
                'settings.manage' => 'Pengaturan',
            ],
        ],
        [
            'key' => 'dashboard_widgets',
            'label' => 'Widget Dashboard',
            'permissions' => [
                'dashboard.widget.kpis' => 'Kartu KPI',
                'dashboard.widget.clear_by_type' => 'Chart komposisi Clear',
                'dashboard.widget.clear_by_noc' => 'Chart Clear per NOC',
                'dashboard.widget.noc_performance' => 'Peringkat kinerja NOC',
                'dashboard.widget.quick_actions' => 'Aksi cepat',
                'dashboard.widget.recent' => 'Aktivitas terbaru',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default permissions per role (seed / migrate)
    |--------------------------------------------------------------------------
    */
    'role_defaults' => [
        'administrator' => ['*'],
        'manager' => [
            'dashboard.view', 'monitoring.view',
            'ticket.view',
            'customer.view', 'package.view',
            'network.view',
            'report.view', 'report.generate', 'analytics.view', 'audit.view',
            'dashboard.widget.kpis', 'dashboard.widget.clear_by_type',
            'dashboard.widget.clear_by_noc', 'dashboard.widget.noc_performance',
            'dashboard.widget.quick_actions', 'dashboard.widget.recent',
        ],
        'noc' => [
            'dashboard.view', 'monitoring.view',
            'complaint.view', 'activation.view', 'dismantle.view',
            'ticket.view', 'ticket.manage',
            'customer.view', 'network.view',
            'report.generate', 'report.view',
            'dashboard.widget.kpis', 'dashboard.widget.clear_by_type',
            'dashboard.widget.clear_by_noc', 'dashboard.widget.noc_performance',
            'dashboard.widget.quick_actions', 'dashboard.widget.recent',
        ],
        'engineer' => [
            'dashboard.view', 'monitoring.view',
            'network.view', 'network.manage',
            'dashboard.widget.kpis', 'dashboard.widget.recent',
        ],
        'teknisi' => [
            'complaint.view', 'activation.view', 'dismantle.view',
            'ticket.view', 'ticket.manage',
            'customer.view',
        ],
        'finance' => [
            'dashboard.view', 'customer.view', 'report.view',
            'dashboard.widget.kpis', 'dashboard.widget.recent',
        ],
    ],
];
