<?php

namespace App\Services\Report;

use App\Models\Customer;
use App\Models\DailyActivation;
use App\Models\DailyCctvSetup;
use App\Models\DailyComplaint;
use App\Models\DailyDismantle;
use App\Models\DailyNocUpdate;
use App\Models\ReportTemplate;
use App\Support\ReportStatus;
use App\Support\SimpleTemplateEngine;
use App\Support\AppSetting;
use App\Support\ReportTemplateDefaults;
use Carbon\Carbon;

class ReportGeneratorService
{
    /** @var array<string, string> */
    private array $pppoeByName = [];

    public function __construct(
        private ReportTemplateService $templates,
        private SimpleTemplateEngine $engine,
    ) {}

    public function generateDailyReport(Carbon $date, string $responsibleName): string
    {
        $this->pppoeByName = [];

        $context = [
            'responsible_name' => $responsibleName,
            'activity_name' => AppSetting::get(
                'activity_name',
                config('app.activity_name', 'Report Monitoring & Aktivasi Broadband'),
            ),
            'activations' => $this->dailyActivations($date),
            'cctv_setups' => $this->dailyCctvSetups($date),
            'dismantles' => $this->dailyDismantles($date),
            'complaints_by_odc' => $this->dailyComplaintsByOdc($date),
        ];

        $body = $this->templates->bodyFor(ReportTemplate::TYPE_DAILY);

        return $this->normalizeReportText($this->engine->render($body, $context));
    }

    public function generateNocUpdate(Carbon $date): string
    {
        $this->pppoeByName = [];

        $context = $this->nocContext($date);
        $body = $this->templates->bodyFor(ReportTemplate::TYPE_NOC);

        return $this->normalizeReportText($this->engine->render($body, $context));
    }

    /**
     * Generate teks report hanya untuk satu bagian (komplain, aktivasi, dll).
     *
     * @param  'complaint'|'activation'|'cctv'|'noc'|'dismantle'|'ticket'|'monitoring'  $section
     */
    public function generateSection(string $section, Carbon $date): string
    {
        $this->pppoeByName = [];

        if ($section === 'monitoring') {
            throw new \InvalidArgumentException('Section monitoring ditangani oleh NetworkMonitorReportService.');
        }

        $body = ReportTemplateDefaults::sectionBody($section);
        if ($body === '') {
            throw new \InvalidArgumentException("Section report tidak dikenal: {$section}");
        }

        $context = match ($section) {
            'complaint' => ['complaints_by_odc' => $this->dailyComplaintsByOdc($date)],
            'activation' => ['activations' => $this->dailyActivations($date)],
            'cctv' => ['cctv_setups' => $this->dailyCctvSetups($date)],
            'noc' => $this->nocUpdateOnlyContext($date),
            'dismantle' => ['dismantles' => $this->moduleDismantles($date)],
            'ticket' => ['tickets' => $this->reportTickets($date)],
            default => [],
        };

        return $this->normalizeReportText($this->engine->render($body, $context));
    }

    /** @return array<string, mixed> */
    private function nocContext(Carbon $date): array
    {
        $updates = DailyNocUpdate::whereDate('report_date', $date)->orderBy('sort_order')->get();
        $onProgress = $updates->filter(fn ($u) => ! ReportStatus::isClear($u->status))->values();
        $cleared = $updates->filter(fn ($u) => ReportStatus::isClear($u->status))->values();

        $dismantles = DailyDismantle::whereDate('report_date', $date)->get();

        $openBySite = $dismantles->filter(fn ($d) => ! ReportStatus::isClear($d->status))
            ->groupBy(fn ($d) => $d->site_name ?: 'Tanpa Site');

        $clearedBySite = $dismantles->filter(fn ($d) => ReportStatus::isClear($d->status))
            ->groupBy(fn ($d) => $d->site_name ?: 'Tanpa Site');

        $activationCount = DailyActivation::whereDate('report_date', $date)->count();

        return [
            'noc_on_progress' => $onProgress->map(fn ($u) => ['description' => $u->description])->all(),
            'has_noc_cleared' => $cleared->isNotEmpty(),
            'noc_cleared' => $cleared->map(fn ($u) => ['description' => $u->description])->all(),
            'activation_line' => $activationCount > 0 ? "{$activationCount} aktivasi hari ini" : '-',
            'dismantle_open' => $openBySite->map(fn ($items, $siteName) => [
                'site' => $siteName,
                'count' => $items->count(),
            ])->values()->all(),
            'has_dismantle_cleared' => $clearedBySite->isNotEmpty(),
            'dismantle_cleared' => $clearedBySite->map(fn ($items, $siteName) => [
                'site' => $siteName,
                'count' => $items->count(),
            ])->values()->all(),
            'odc_complaints' => $this->nocComplaintsByOdc($date),
        ];
    }

    /** @return array<string, mixed> */
    private function nocUpdateOnlyContext(Carbon $date): array
    {
        $updates = DailyNocUpdate::whereDate('report_date', $date)->orderBy('sort_order')->get();
        $onProgress = $updates->filter(fn ($u) => ! ReportStatus::isClear($u->status))->values();
        $cleared = $updates->filter(fn ($u) => ReportStatus::isClear($u->status))->values();

        return [
            'noc_on_progress' => $onProgress->map(fn ($u) => ['description' => $u->description])->all(),
            'has_noc_cleared' => $cleared->isNotEmpty(),
            'noc_cleared' => $cleared->map(fn ($u) => ['description' => $u->description])->all(),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function moduleDismantles(Carbon $date): array
    {
        return \App\Models\Dismantle::query()
            ->whereDate('opened_at', $date)
            ->orderByRaw("CASE WHEN status = 'Clear' THEN 1 ELSE 0 END")
            ->orderBy('opened_at')
            ->get()
            ->map(function ($d) {
                $name = $this->formatCustomerName($d->customer_name);
                if ($d->location) {
                    $name .= ' ('.$d->location.')';
                }

                return [
                    'customer_name' => $name,
                    'start_ticket' => $this->formatDate($d->opened_at),
                    'close_ticket' => $this->formatDate($d->closed_at, true),
                    'status' => $d->status,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private function reportTickets(Carbon $date): array
    {
        return \App\Models\ReportTicket::query()
            ->whereDate('opened_at', $date)
            ->orderByRaw("CASE WHEN status = 'Clear' OR status = 'Closed' THEN 1 ELSE 0 END")
            ->orderBy('opened_at')
            ->get()
            ->map(fn ($t) => [
                'odc_name' => $t->odc_name ?: '-',
                'location' => $t->location ?: '-',
                'customer_code' => $t->customer_code ?: '-',
                'customer_name' => $t->customer_name ?: '-',
                'problem' => $t->problem ?: '-',
                'action' => $t->action ?: '-',
                'status' => $t->status ?: '-',
                'opened_at' => $this->formatDate($t->opened_at),
                'closed_at' => $this->formatDate($t->closed_at, true),
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private function dailyActivations(Carbon $date): array
    {
        return DailyActivation::whereDate('report_date', $date)
            ->orderBy('customer_name')
            ->get()
            ->map(fn ($item) => [
                'customer_name' => $item->customer_name,
                'olt' => $item->olt_name ?? '-',
                'port_onu' => $item->port_onu ?? '-',
                'status' => $item->status,
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private function dailyCctvSetups(Carbon $date): array
    {
        return DailyCctvSetup::whereDate('report_date', $date)
            ->get()
            ->map(fn ($cctv) => [
                'customer_name' => $this->formatCustomerName($cctv->customer_name ?: '-'),
                'router' => $cctv->router ?: '-',
                'status' => $cctv->status ?: '-',
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private function dailyDismantles(Carbon $date): array
    {
        return DailyDismantle::whereDate('report_date', $date)
            ->orderByRaw("CASE WHEN status = 'Clear' THEN 1 ELSE 0 END")
            ->orderBy('start_ticket')
            ->get()
            ->map(function ($d) {
                $name = $this->formatCustomerName($d->customer_name);
                if ($d->site_name) {
                    $name .= ' ('.$d->site_name.')';
                }

                return [
                    'customer_name' => $name,
                    'start_ticket' => $this->formatDate($d->start_ticket),
                    'close_ticket' => $this->formatDate($d->close_ticket, true),
                    'status' => $d->status,
                ];
            })
            ->all();
    }

    /** @return array<int, array{odc_name: string, items: array<int, array<string, string>>}> */
    private function dailyComplaintsByOdc(Carbon $date): array
    {
        $complaints = DailyComplaint::whereDate('report_date', $date)
            ->orderByRaw("CASE WHEN status = 'Clear' THEN 1 ELSE 0 END")
            ->orderBy('start_problem')
            ->get();

        return $complaints->groupBy(fn ($c) => $c->odc_name ?: 'Tanpa ODC')
            ->map(fn ($items, $odcName) => [
                'odc_name' => $odcName,
                'items' => $items->map(fn ($c) => [
                    'customer_name' => $c->displayLabel(),
                    'customer_code' => $c->customer_code ?? '-',
                    'complaint_type' => $c->complaint_type ?? 'individual',
                    'start_problem' => $this->formatDate($c->start_problem),
                    'end_problem' => $this->formatDate($c->end_problem, true),
                    'problem' => $c->problem ?? '-',
                    'action' => $c->action ?? '-',
                    'status' => $c->status,
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function nocComplaintsByOdc(Carbon $date): array
    {
        $complaints = DailyComplaint::whereDate('report_date', $date)->get();

        return $complaints->groupBy(fn ($c) => $c->odc_name ?: 'Tanpa ODC')
            ->map(function ($items, $odcName) {
                $open = $items->filter(fn ($c) => ! ReportStatus::isClear($c->status));
                $clear = $items->filter(fn ($c) => ReportStatus::isClear($c->status));

                return [
                    'odc_name' => $odcName,
                    'open_items' => $open->map(fn ($c) => [
                        'customer_name' => $c->displayLabel(),
                        'problem' => $c->problem ?? '-',
                        'action' => $c->action ?? '-',
                    ])->values()->all(),
                    'has_clear' => $clear->isNotEmpty(),
                    'clear_items' => $clear->map(fn ($c) => [
                        'customer_name' => $c->displayLabel(),
                        'problem' => $c->problem ?? '-',
                        'action' => $c->action ?? '-',
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function formatDate(?Carbon $date, bool $allowDash = false): string
    {
        if (! $date) {
            return $allowDash ? '-' : '-';
        }

        return $date->format('d/m/Y');
    }

    private function formatCustomerName(?string $customerName, ?string $odcName = null): string
    {
        $displayName = trim((string) $customerName);
        if ($displayName === '' || $displayName === '-') {
            return '-';
        }

        $this->loadPppoeMap();
        $baseName = $this->extractBaseName($displayName);
        $key = mb_strtolower($baseName);
        $pppoe = $this->pppoeByName[$key] ?? null;

        if ($pppoe) {
            return $pppoe.' '.$baseName;
        }

        return $displayName;
    }

    private function extractBaseName(string $customerName): string
    {
        if (preg_match('/^(.+?)\s*\([^)]+\)\s*$/u', trim($customerName), $matches)) {
            return trim($matches[1]);
        }

        return trim($customerName);
    }

    private function loadPppoeMap(): void
    {
        if ($this->pppoeByName !== []) {
            return;
        }

        Customer::query()
            ->whereNotNull('pppoe')
            ->where('pppoe', '!=', '')
            ->get(['name', 'pppoe'])
            ->each(function ($customer) {
                $key = mb_strtolower(trim($customer->name));
                if ($key !== '') {
                    $this->pppoeByName[$key] = $customer->pppoe;
                }
            });
    }

    private function normalizeReportText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text)."\n";
    }
}
