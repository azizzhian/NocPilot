<?php

namespace Database\Seeders;

use App\Models\Activation;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Dismantle;
use App\Models\InternetPackage;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Pop;
use App\Models\RealtimeEvent;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Ticket\TicketSlaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [];
        foreach (config('permissions.groups', []) as $group) {
            foreach (array_keys($group['permissions'] ?? []) as $name) {
                $permissions[] = $name;
            }
        }

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = config('permissions.role_defaults', []);

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if (in_array('*', $perms, true)) {
                $role->syncPermissions($permissions);
            } else {
                $role->syncPermissions(array_values(array_intersect($perms, $permissions)));
            }
        }

        $users = [
            ['name' => 'Admin NOC', 'username' => 'admin', 'email' => 'admin@nocpilot.id', 'role' => 'administrator', 'department' => 'IT'],
            ['name' => 'Operator NOC', 'username' => 'noc', 'email' => 'noc@nocpilot.id', 'role' => 'noc', 'department' => 'Network Operations'],
            ['name' => 'Manager Ops', 'username' => 'manager', 'email' => 'manager@nocpilot.id', 'role' => 'manager', 'department' => 'Operations'],
            ['name' => 'Ahmad Rizki', 'username' => 'teknisi', 'email' => 'teknisi@nocpilot.id', 'role' => 'teknisi', 'department' => 'Field Service'],
            ['name' => 'Budi Engineer', 'username' => 'engineer', 'email' => 'engineer@nocpilot.id', 'role' => 'engineer', 'department' => 'Network Engineering'],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                    'department' => $data['department'],
                    'status' => 'active',
                ],
            );
            $user->syncRoles([$data['role']]);
        }

        if (Customer::count() === 0) {
            $demoCustomers = [
                ['customer_code' => 'PLG-00001', 'name' => 'Budi Santoso', 'pppoe' => 'budi@net', 'package' => '50 Mbps', 'status' => 'active', 'area' => 'Cibaduyut', 'odp' => 'ODP-JL-SUDIRMAN-07', 'rx_power' => -22.5, 'tx_power' => 2.1, 'phone' => '081234567890'],
                ['customer_code' => 'PLG-00002', 'name' => 'Sari Dewi', 'pppoe' => 'sari@net', 'package' => '30 Mbps', 'status' => 'active', 'area' => 'Cimahi', 'odp' => 'ODP-CIMAHI-12', 'rx_power' => -24.1, 'tx_power' => 1.8, 'phone' => '081398765432'],
                ['customer_code' => 'PLG-00003', 'name' => 'Andi Wijaya', 'pppoe' => 'andi@net', 'package' => '100 Mbps', 'status' => 'suspended', 'area' => 'Dago', 'odp' => 'ODP-DAGO-03', 'rx_power' => -28.5, 'tx_power' => 2.5, 'phone' => '081511223344'],
                ['customer_code' => 'PLG-00004', 'name' => 'Rina Kartika', 'pppoe' => 'rina@net', 'package' => '20 Mbps', 'status' => 'active', 'area' => 'Ujung Berung', 'odp' => 'ODP-UB-08', 'rx_power' => -21.3, 'tx_power' => 1.5, 'phone' => '081655667788'],
                ['customer_code' => 'PLG-00005', 'name' => 'Dedi Kurniawan', 'pppoe' => 'dedi@net', 'package' => '50 Mbps', 'status' => 'inactive', 'area' => 'Bandung', 'odp' => 'ODP-BDG-15', 'rx_power' => 0, 'tx_power' => 0, 'phone' => '081799887766'],
            ];

            foreach ($demoCustomers as $customer) {
                Customer::create([...$customer, 'activated_at' => now()->subMonths(rand(1, 24))]);
            }

            Customer::factory()->count(45)->create();
        }

        if (Router::count() === 0) {
            $routers = [
                ['name' => 'RT-CORE-01', 'ip' => '10.0.0.1', 'pop' => 'POP Bandung', 'area' => 'Bandung', 'status' => 'online', 'cpu' => 72, 'memory' => 68, 'temperature' => 42, 'uptime' => '45d 12h', 'clients' => 856, 'pppoe_sessions' => 420, 'board' => 'CCR1036-12G-4S', 'version' => '7.12.1', 'license' => 'Level 6'],
                ['name' => 'RT-POP-01', 'ip' => '10.0.1.1', 'pop' => 'POP Cimahi', 'area' => 'Cimahi', 'status' => 'online', 'cpu' => 45, 'memory' => 52, 'temperature' => 38, 'uptime' => '30d 8h', 'clients' => 324, 'pppoe_sessions' => 198, 'board' => 'RB4011iGS+', 'version' => '7.11.2', 'license' => 'Level 4'],
                ['name' => 'RT-POP-02', 'ip' => '10.0.2.1', 'pop' => 'POP Cibaduyut', 'area' => 'Cibaduyut', 'status' => 'online', 'cpu' => 38, 'memory' => 48, 'temperature' => 36, 'uptime' => '28d 4h', 'clients' => 256, 'pppoe_sessions' => 145, 'board' => 'RB3011UiAS', 'version' => '7.10.1', 'license' => 'Level 4'],
                ['name' => 'RT-POP-03', 'ip' => '10.0.3.1', 'pop' => 'POP Ujung Berung', 'area' => 'Ujung Berung', 'status' => 'offline', 'cpu' => 0, 'memory' => 0, 'temperature' => 0, 'uptime' => '-', 'clients' => 0, 'pppoe_sessions' => 0, 'board' => 'RB4011iGS+', 'version' => '7.11.2', 'license' => 'Level 4'],
                ['name' => 'RT-POP-04', 'ip' => '10.0.4.1', 'pop' => 'POP Dago', 'area' => 'Dago', 'status' => 'online', 'cpu' => 55, 'memory' => 60, 'temperature' => 40, 'uptime' => '22d 16h', 'clients' => 412, 'pppoe_sessions' => 267, 'board' => 'RB4011iGS+', 'version' => '7.12.1', 'license' => 'Level 4'],
            ];

            foreach ($routers as $router) {
                Router::create([
                    ...$router,
                    'download_bps' => match ($router['status']) {
                        'online' => rand(80_000_000, 400_000_000),
                        default => 0,
                    },
                    'upload_bps' => match ($router['status']) {
                        'online' => rand(30_000_000, 150_000_000),
                        default => 0,
                    },
                    'last_synced_at' => now(),
                ]);
            }
        }

        if (Ticket::count() === 0) {
            $sla = app(TicketSlaService::class);
            $teknisi = User::where('email', 'teknisi@nocpilot.id')->first();
            $customers = Customer::take(4)->get();

            $tickets = [
                ['subject' => 'Internet lambat — Cibaduyut', 'priority' => 'high', 'status' => 'open', 'area' => 'Cibaduyut'],
                ['subject' => 'PPPoE tidak bisa connect', 'priority' => 'critical', 'status' => 'assigned', 'area' => 'Cimahi'],
                ['subject' => 'ONU LOS — tidak ada sinyal', 'priority' => 'critical', 'status' => 'progress', 'area' => 'Dago'],
                ['subject' => 'Upgrade paket 50Mbps', 'priority' => 'low', 'status' => 'open', 'area' => 'Ujung Berung'],
                ['subject' => 'WiFi lemot di malam hari', 'priority' => 'medium', 'status' => 'solved', 'area' => 'Bandung'],
            ];

            foreach ($tickets as $i => $data) {
                $customer = $customers[$i] ?? null;
                Ticket::create([
                    'ticket_number' => Ticket::generateNumber(),
                    'subject' => $data['subject'],
                    'description' => 'Laporan gangguan dari pelanggan.',
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name ?? 'Pelanggan',
                    'customer_phone' => $customer?->phone,
                    'priority' => $data['priority'],
                    'status' => $data['status'],
                    'area' => $data['area'],
                    'sla_deadline' => $sla->calculateDeadline($data['priority']),
                    'assigned_to' => in_array($data['status'], ['assigned', 'progress']) ? $teknisi?->id : null,
                    'assigned_at' => in_array($data['status'], ['assigned', 'progress']) ? now()->subHours(1) : null,
                    'solved_at' => $data['status'] === 'solved' ? now()->subHours(2) : null,
                    'created_by' => User::where('email', 'noc@nocpilot.id')->value('id'),
                ]);
            }
        }

        if (Activation::count() === 0) {
            $teknisi = User::where('email', 'teknisi@nocpilot.id')->first();
            $customers = Customer::where('status', 'inactive')->take(2)->get();

            foreach ($customers as $i => $customer) {
                Activation::create([
                    'reference' => Activation::generateReference(),
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'phone' => $customer->phone,
                    'package' => '50 Mbps',
                    'area' => $customer->area,
                    'status' => $i === 0 ? 'pending' : 'scheduled',
                    'scheduled_at' => $i === 1 ? now()->addDays(2) : null,
                    'assigned_to' => $teknisi?->id,
                    'created_by' => User::where('email', 'noc@nocpilot.id')->value('id'),
                ]);
            }

            Activation::create([
                'reference' => Activation::generateReference(),
                'customer_name' => 'Pelanggan Baru — Jl. Asia Afrika',
                'phone' => '0812-0000-1111',
                'package' => '30 Mbps',
                'area' => 'Bandung',
                'status' => 'pending',
                'created_by' => User::where('email', 'noc@nocpilot.id')->value('id'),
            ]);
        }

        if (Dismantle::count() === 0) {
            $teknisi = User::where('email', 'teknisi@nocpilot.id')->first();
            $customer = Customer::where('status', 'suspended')->first();

            if ($customer) {
                Dismantle::create([
                    'reference' => Dismantle::generateReference(),
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'phone' => $customer->phone,
                    'pppoe' => $customer->pppoe,
                    'package' => $customer->package,
                    'area' => $customer->area,
                    'reason' => 'Tunggakan pembayaran 3 bulan',
                    'status' => 'pending',
                    'assigned_to' => $teknisi?->id,
                    'created_by' => User::where('email', 'noc@nocpilot.id')->value('id'),
                ]);
            }

            Dismantle::create([
                'reference' => Dismantle::generateReference(),
                'customer_name' => 'Eko Prasetyo',
                'pppoe' => 'eko@net',
                'package' => '20 Mbps',
                'area' => 'Cimahi',
                'reason' => 'Pindah kota',
                'status' => 'scheduled',
                'scheduled_at' => now()->addDays(3),
                'created_by' => User::where('email', 'noc@nocpilot.id')->value('id'),
            ]);
        }

        if (ActivityLog::count() === 0) {
            $admin = User::where('email', 'admin@nocpilot.id')->first();
            $noc = User::where('email', 'noc@nocpilot.id')->first();
            $engineer = User::where('email', 'engineer@nocpilot.id')->first();

            $samples = [
                ['user_id' => $admin?->id, 'user_name' => 'Admin NOC', 'type' => 'login', 'action' => 'Login berhasil', 'ip_address' => '192.168.1.100', 'browser' => 'Chrome', 'device' => 'Windows', 'created_at' => now()->subMinutes(15)],
                ['user_id' => $engineer?->id, 'user_name' => 'Budi Engineer', 'type' => 'router', 'action' => 'Sinkronisasi RT-CORE-01', 'ip_address' => '192.168.1.105', 'browser' => 'Firefox', 'device' => 'Windows', 'created_at' => now()->subMinutes(30)],
                ['user_id' => $admin?->id, 'user_name' => 'Admin NOC', 'type' => 'customer', 'action' => 'Edit pelanggan Budi Santoso', 'ip_address' => '192.168.1.100', 'browser' => 'Chrome', 'device' => 'Windows', 'created_at' => now()->subMinutes(45)],
                ['user_id' => null, 'user_name' => 'System', 'type' => 'backup', 'action' => 'Backup RT-CORE-01 otomatis', 'ip_address' => '127.0.0.1', 'browser' => '—', 'device' => 'Server', 'created_at' => now()->subHour()],
                ['user_id' => $noc?->id, 'user_name' => 'Operator NOC', 'type' => 'ticket', 'action' => 'Buat ticket TKT-00001', 'ip_address' => '192.168.1.110', 'browser' => 'Chrome', 'device' => 'macOS', 'created_at' => now()->subHours(2)],
            ];

            foreach ($samples as $sample) {
                ActivityLog::create($sample);
            }
        }

        if (RealtimeEvent::count() === 0) {
            $events = [
                ['event' => 'router.offline', 'title' => 'Router Offline', 'message' => 'RT-POP-03 tidak merespons ping', 'severity' => 'critical'],
                ['event' => 'ticket.created', 'title' => 'Ticket Baru', 'message' => 'PPPoE tidak bisa connect — Cimahi', 'severity' => 'warning'],
                ['event' => 'sync.complete', 'title' => 'Sinkronisasi Selesai', 'message' => '4 router berhasil disinkronkan', 'severity' => 'success'],
            ];

            foreach ($events as $event) {
                RealtimeEvent::create([...$event, 'channel' => 'noc']);
            }
        }

        if (Pop::count() === 0) {
            $popBandung = Pop::create(['name' => 'POP Bandung', 'code' => 'POP-BDG', 'area' => 'Bandung', 'status' => 'active', 'capacity' => 2000]);
            $popCimahi = Pop::create(['name' => 'POP Cimahi', 'code' => 'POP-CMH', 'area' => 'Cimahi', 'status' => 'active', 'capacity' => 1500]);
            $popCibaduyut = Pop::create(['name' => 'POP Cibaduyut', 'code' => 'POP-CBD', 'area' => 'Cibaduyut', 'status' => 'active', 'capacity' => 1200]);

            $odc1 = Odc::create(['pop_id' => $popCibaduyut->id, 'name' => 'ODC-CIBADUYUT-03', 'code' => 'ODC-CBD-03', 'status' => 'active', 'capacity' => 500, 'location' => 'Cibaduyut']);
            $odc2 = Odc::create(['pop_id' => $popCimahi->id, 'name' => 'ODC-CIMAHI-12', 'code' => 'ODC-CMH-12', 'status' => 'active', 'capacity' => 400, 'location' => 'Cimahi']);

            $odp1 = Odp::create(['odc_id' => $odc1->id, 'name' => 'ODP-JL-SUDIRMAN-07', 'code' => 'ODP-SDR-07', 'status' => 'active', 'capacity' => 32, 'used_ports' => 28]);
            $odp2 = Odp::create(['odc_id' => $odc2->id, 'name' => 'ODP-CIMAHI-12', 'code' => 'ODP-CMH-12', 'status' => 'active', 'capacity' => 16, 'used_ports' => 10]);
            Odp::create(['odc_id' => $odc1->id, 'name' => 'ODP-DAGO-03', 'code' => 'ODP-DGO-03', 'status' => 'full', 'capacity' => 16, 'used_ports' => 16]);

            $olt1 = Olt::create(['pop_id' => $popBandung->id, 'name' => 'OLT-POP-BANDUNG-01', 'ip' => '10.10.1.10', 'status' => 'online', 'capacity' => 128, 'pon_ports' => 8]);
            Olt::create(['pop_id' => $popCimahi->id, 'name' => 'OLT-POP-CIMAHI-01', 'ip' => '10.10.2.10', 'status' => 'online', 'capacity' => 128, 'pon_ports' => 8]);
            Olt::create(['pop_id' => $popCibaduyut->id, 'name' => 'OLT-POP-CIBADUYUT-01', 'ip' => '10.10.3.10', 'status' => 'offline', 'capacity' => 128, 'pon_ports' => 8]);

            $budi = Customer::where('customer_code', 'PLG-00001')->first();
            $sari = Customer::where('customer_code', 'PLG-00002')->first();
            $andi = Customer::where('customer_code', 'PLG-00003')->first();

            $budi?->update(['odc_id' => $odc1->id]);
            $sari?->update(['odc_id' => $odc2->id]);
            $andi?->update(['odc_id' => $odc1->id]);

            Onu::create(['odp_id' => $odp1->id, 'olt_id' => $olt1->id, 'customer_id' => $budi?->id, 'serial' => 'HWTC12345678', 'name' => 'ONU-ODP-12-045', 'status' => 'offline', 'rx_power' => -28.5, 'tx_power' => 2.1, 'pon_port' => '0/1/1']);
            Onu::create(['odp_id' => $odp2->id, 'olt_id' => $olt1->id, 'customer_id' => $sari?->id, 'serial' => 'HWTC87654321', 'name' => 'ONU-CIMAHI-001', 'status' => 'online', 'rx_power' => -24.1, 'tx_power' => 1.8, 'pon_port' => '0/1/2']);
            Onu::create(['odp_id' => $odp1->id, 'customer_id' => $andi?->id, 'serial' => 'ZTE99887766', 'name' => 'ONU-DAGO-003', 'status' => 'los', 'rx_power' => -35.0, 'tx_power' => 0, 'pon_port' => '0/1/3']);
        }

        if (InternetPackage::count() === 0) {
            $packages = [
                ['name' => 'Home 20 Mbps', 'speed_mbps' => 20, 'price' => 150000, 'description' => 'Paket rumahan basic'],
                ['name' => 'Home 30 Mbps', 'speed_mbps' => 30, 'price' => 200000, 'description' => 'Paket rumahan standar'],
                ['name' => 'Home 50 Mbps', 'speed_mbps' => 50, 'price' => 275000, 'description' => 'Paket rumahan premium'],
                ['name' => 'Business 100 Mbps', 'speed_mbps' => 100, 'price' => 500000, 'description' => 'Paket bisnis UMKM'],
            ];
            foreach ($packages as $pkg) {
                InternetPackage::create([...$pkg, 'status' => 'active']);
            }
        }
    }
}
