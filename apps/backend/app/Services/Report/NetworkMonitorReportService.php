<?php

namespace App\Services\Report;

use App\Models\ReportTemplate;
use App\Models\Router;
use App\Models\RouterInterface;
use App\Support\BandwidthFormatter;
use App\Support\ReportTemplateDefaults;
use App\Support\SimpleTemplateEngine;
use Illuminate\Support\Collection;

class NetworkMonitorReportService
{
    public function __construct(
        private ReportTemplateService $templates,
        private SimpleTemplateEngine $engine,
    ) {}

    public function generate(): string
    {
        $sectionTemplates = $this->templates->parseSectionedTemplate(
            $this->templates->bodyFor(ReportTemplate::TYPE_MONITORING),
        );

        $lines = [];

        $routers = Router::query()
            ->where('is_active', true)
            ->with(['monitoredInterfaces' => fn ($q) => $q->orderBy('interface_name')])
            ->orderByRaw("CASE WHEN UPPER(name) LIKE '%CORE%' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        foreach ($routers as $router) {
            try {
                $block = $router->isOnline()
                    ? $this->formatOnlineRouter($router, $sectionTemplates)
                    : $this->formatOfflineRouter($router, 'Router tidak merespons', $sectionTemplates);
            } catch (\Throwable $e) {
                $block = $this->formatOfflineRouter($router, $e->getMessage(), $sectionTemplates);
            }

            foreach (preg_split('/\R/', trim($block)) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }

            $lines[] = '';
        }

        if ($routers->isEmpty()) {
            $lines[] = 'Belum ada router aktif terdaftar.';
        }

        return $this->normalizeReportText(implode("\n", $lines));
    }

    /** @param array<string, string> $sectionTemplates */
    private function formatOnlineRouter(Router $router, array $sectionTemplates): string
    {
        $isCore = str_contains(strtoupper($router->name), 'CORE');
        $cpu = (int) $router->cpu;
        $trafficCtx = $this->monitoredTrafficContext($router->monitoredInterfaces);
        $ifaceCount = count($trafficCtx['interfaces']);

        $context = [
            'name' => $router->name,
            'cpu' => $cpu,
            'traffic' => $trafficCtx['traffic'],
            'interfaces' => $trafficCtx['interfaces'],
            'iface_name' => $trafficCtx['interfaces'][0]['iface_name'] ?? '',
            'iface_count' => $ifaceCount,
        ];

        if ($isCore) {
            return $this->renderSection($sectionTemplates, $ifaceCount > 1 ? 'multi' : 'core', $context);
        }

        if ($ifaceCount > 1) {
            return $this->renderSection($sectionTemplates, 'multi', $context);
        }

        if ($ifaceCount === 1) {
            return $this->renderSection($sectionTemplates, 'single', $context);
        }

        return $this->renderSection($sectionTemplates, 'single_empty', $context);
    }

    /**
     * @param  Collection<int, RouterInterface>  $interfaces
     * @return array{interfaces: list<array<string, mixed>>, traffic: string}
     */
    private function monitoredTrafficContext(Collection $interfaces): array
    {
        if ($interfaces->isEmpty()) {
            return [
                'interfaces' => [],
                'traffic' => '0 bps / 0 bps',
            ];
        }

        $items = [];
        foreach ($interfaces as $iface) {
            $rx = (int) ($iface->rx_bps ?? 0);
            $tx = (int) ($iface->tx_bps ?? 0);
            $pair = BandwidthFormatter::pair($rx, $tx);
            $items[] = [
                'iface_name' => $iface->displayName(),
                'interface_name' => $iface->interface_name,
                'traffic' => $pair,
            ];
        }

        $traffic = count($items) === 1
            ? $items[0]['traffic']
            : implode("\n", array_map(
                fn (array $item) => $item['iface_name'].': '.$item['traffic'],
                $items,
            ));

        return [
            'interfaces' => $items,
            'traffic' => $traffic,
        ];
    }

    /** @param array<string, string> $sectionTemplates */
    private function formatOfflineRouter(Router $router, string $message, array $sectionTemplates): string
    {
        $key = str_contains(strtoupper($router->name), 'CORE') ? 'offline_core' : 'offline';

        return $this->renderSection($sectionTemplates, $key, [
            'name' => $router->name,
            'message' => $message,
        ]);
    }

    /** @param array<string, string> $sectionTemplates */
    /** @param array<string, mixed> $context */
    private function renderSection(array $sectionTemplates, string $key, array $context): string
    {
        $template = $sectionTemplates[$key] ?? $this->fallbackSection($key);

        return trim($this->engine->render($template, $context));
    }

    private function fallbackSection(string $key): string
    {
        $defaults = $this->templates->parseSectionedTemplate(
            ReportTemplateDefaults::body(ReportTemplate::TYPE_MONITORING),
        );

        return $defaults[$key] ?? '';
    }

    private function normalizeReportText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text)."\n";
    }
}