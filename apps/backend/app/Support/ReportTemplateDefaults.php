<?php

namespace App\Support;

use App\Models\ReportTemplate;

class ReportTemplateDefaults
{
    public static function body(string $type): string
    {
        return match ($type) {
            ReportTemplate::TYPE_DAILY => self::daily(),
            ReportTemplate::TYPE_NOC => self::noc(),
            ReportTemplate::TYPE_MONITORING => self::monitoring(),
            default => '',
        };
    }

    public static function hints(string $type): array
    {
        return match ($type) {
            ReportTemplate::TYPE_DAILY => [
                '{{responsible_name}}' => 'Nama penanggung jawab',
                '{{activity_name}}' => 'Nama aktivitas',
                '{{#activations}}...{{/activations}}' => 'Loop data aktivasi',
                '{{#cctv_setups}}...{{/cctv_setups}}' => 'Loop setup CCTV',
                '{{^cctv_setups}}...{{/cctv_setups}}' => 'Tampil jika CCTV kosong',
                '{{#dismantles}}...{{/dismantles}}' => 'Loop dismantle',
                '{{#complaints_by_odc}}...{{/complaints_by_odc}}' => 'Loop komplain per ODC',
                '{{#items}}...{{/items}}' => 'Loop item dalam ODC',
            ],
            ReportTemplate::TYPE_NOC => [
                '{{#noc_on_progress}}...{{/noc_on_progress}}' => 'Update NOC on-progress',
                '{{#has_noc_cleared}}...{{/has_noc_cleared}}' => 'Blok Clear update NOC',
                '{{activation_line}}' => 'Baris aktivasi (default: -)',
                '{{#dismantle_open}}...{{/dismantle_open}}' => 'Dismantle on-progress per site',
                '{{#odc_complaints}}...{{/odc_complaints}}' => 'Komplain per ODC',
            ],
            ReportTemplate::TYPE_MONITORING => [
                '@@core@@' => 'Format router core',
                '@@multi@@' => 'Router dengan >1 interface terpantau',
                '@@single@@' => 'Router 1 interface terpantau',
                '@@single_empty@@' => 'Router tanpa interface terpantau',
                '@@offline_core@@' => 'Core router offline',
                '@@offline@@' => 'Router non-core offline',
                '{{#interfaces}}...{{/interfaces}}' => 'Loop interface yang dicentang',
                '{{iface_name}}' => 'Nama interface',
                '{{traffic}}' => 'RX / TX interface (atau ringkasan)',
            ],
            default => [],
        };
    }

    /** Template teks untuk generate per bagian (bukan full daily). */
    public static function sectionBody(string $section): string
    {
        return match ($section) {
            'complaint' => <<<'TPL'
Komplain Pelanggan:

{{#complaints_by_odc}}
ODC {{odc_name}}:

{{#items}}
Nama Pelanggan: {{customer_name}}
Start Problem: {{start_problem}}
End Problem: {{end_problem}}
Problem: {{problem}}
Action: {{action}}
Status: {{status}}

{{/items}}
{{/complaints_by_odc}}
TPL,
            'activation' => <<<'TPL'
Aktivasi Pelanggan:

{{#activations}}
{{customer_name}}
OLT: {{olt}}
Port | ONU: {{port_onu}}
Status: {{status}}

{{/activations}}
TPL,
            'cctv' => <<<'TPL'
SETUP CCTV

{{#cctv_setups}}
Nama Pelanggan: {{customer_name}}
Router: {{router}}
Status: {{status}}

{{/cctv_setups}}
{{^cctv_setups}}
Nama Pelanggan: -
Router: -
Status: -

{{/cctv_setups}}
TPL,
            'noc' => <<<'TPL'
Update NOC
{{#noc_on_progress}}
* {{description}}
{{/noc_on_progress}}
{{#has_noc_cleared}}
Clear
{{#noc_cleared}}
* {{description}}
{{/noc_cleared}}
{{/has_noc_cleared}}
TPL,
            'dismantle' => <<<'TPL'
Dismantle:

{{#dismantles}}
Nama Pelanggan: {{customer_name}}
Start Ticket: {{start_ticket}}
Close Ticket: {{close_ticket}}
Status: {{status}}

{{/dismantles}}
TPL,
            'ticket' => <<<'TPL'
Report Ticket:

{{#tickets}}
ODC/Site: {{odc_name}}
Lokasi: {{location}}
ID Pel: {{customer_code}}
Nama: {{customer_name}}
Problem: {{problem}}
Action: {{action}}
Status: {{status}}
Tgl Open: {{opened_at}}
Tgl Close: {{closed_at}}

{{/tickets}}
TPL,
            default => '',
        };
    }

    private static function daily(): string
    {
        return <<<'TPL'
Nama: {{responsible_name}}
Nama Aktivitas: {{activity_name}}

Aktivasi Pelanggan:

{{#activations}}
{{customer_name}}
OLT: {{olt}}
Port | ONU: {{port_onu}}
Status: {{status}}

{{/activations}}
SETUP CCTV

{{#cctv_setups}}
Nama Pelanggan: {{customer_name}}
Router: {{router}}
Status: {{status}}

{{/cctv_setups}}
{{^cctv_setups}}
Nama Pelanggan: -
Router: -
Status: -

{{/cctv_setups}}
Dismantle:

{{#dismantles}}
Nama Pelanggan: {{customer_name}}
Start Ticket: {{start_ticket}}
Close Ticket: {{close_ticket}}
Status: {{status}}

{{/dismantles}}
Komplain Pelanggan:

{{#complaints_by_odc}}
ODC {{odc_name}}:

{{#items}}
Nama Pelanggan: {{customer_name}}
Start Problem: {{start_problem}}
End Problem: {{end_problem}}
Problem: {{problem}}
Action: {{action}}
Status: {{status}}

{{/items}}
{{/complaints_by_odc}}
TPL;
    }

    private static function noc(): string
    {
        return <<<'TPL'
Update NOC
{{#noc_on_progress}}
* {{description}}
{{/noc_on_progress}}
{{#has_noc_cleared}}
Clear
{{#noc_cleared}}
* {{description}}
{{/noc_cleared}}
{{/has_noc_cleared}}

Aktivasi Pelanggan:
{{activation_line}}

Dismantle:
{{#dismantle_open}}
* {{site}} = {{count}} user Dismantle
{{/dismantle_open}}
{{#has_dismantle_cleared}}
Clear
{{#dismantle_cleared}}
* {{site}} = {{count}} user Dismantle
{{/dismantle_cleared}}
{{/has_dismantle_cleared}}

Komplain Pelanggan:

{{#odc_complaints}}
ODC {{odc_name}}:
{{#open_items}}
- {{customer_name}} | {{problem}} | {{action}}
{{/open_items}}
{{#has_clear}}
Clear
{{#clear_items}}
- {{customer_name}} | {{problem}} | {{action}}
{{/clear_items}}
{{/has_clear}}

{{/odc_complaints}}
TPL;
    }

    private static function monitoring(): string
    {
        return <<<'TPL'
@@core@@
{{name}}
CPU: {{cpu}}%
{{#interfaces}}
{{iface_name}}: {{traffic}}
{{/interfaces}}
{{^interfaces}}
Traffic 0 bps / 0 bps
{{/interfaces}}

@@multi@@
• {{name}}
CPU: {{cpu}}%
{{#interfaces}}
{{iface_name}}: {{traffic}}
{{/interfaces}}

@@single@@
• CPU {{name}} {{cpu}}% {{iface_name}} {{traffic}}

@@single_empty@@
• CPU {{name}} {{cpu}}% (belum pilih interface)

@@offline_core@@
{{name}}
Status: Offline — {{message}}

@@offline@@
• {{name}}
Status: Offline — {{message}}
TPL;
    }
}
