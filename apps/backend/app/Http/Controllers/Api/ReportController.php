<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use App\Models\Customer;
use App\Models\DailyActivation;
use App\Models\DailyComplaint;
use App\Models\DailyDismantle;
use App\Models\Dismantle;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function analytics(): JsonResponse
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('M'));

        return response()->json([
            'revenue' => [
                'categories' => $months->all(),
                'data' => [450, 480, 520, 510, 560, 590],
            ],
            'customer_growth' => [
                'categories' => $months->all(),
                'data' => collect(range(5, 0))->map(fn ($i) =>
                    Customer::where('created_at', '<=', now()->subMonths($i))->count()
                )->all(),
            ],
            'complaint_trend' => [
                'categories' => $months->all(),
                'data' => collect(range(5, 0))->map(function ($i) {
                    $month = now()->subMonths($i);

                    return DailyComplaint::whereMonth('report_date', $month->month)
                        ->whereYear('report_date', $month->year)
                        ->count();
                })->all(),
            ],
            'top_areas' => Customer::selectRaw('area, count(*) as total')
                ->whereNotNull('area')
                ->groupBy('area')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['name' => $r->area, 'total' => $r->total]),
            'ticket_by_priority' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'activation_vs_dismantle' => [
                'activations' => DailyActivation::count() ?: Activation::count(),
                'dismantles' => DailyDismantle::count() ?: Dismantle::count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->string('type', 'complaints')->toString();
        $filename = "report-{$type}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            match ($type) {
                'customers' => $this->exportCustomers($handle),
                'complaints', 'tickets' => $this->exportComplaints($handle),
                'routers' => $this->exportRouters($handle),
                default => $this->exportComplaints($handle),
            };

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportCustomers($handle): void
    {
        fputcsv($handle, ['Kode Pelanggan', 'Nama Pelanggan', 'Alamat', 'No HP', 'ODC']);
        Customer::with('odc:id,name')->orderBy('name')->chunk(200, function ($rows) use ($handle) {
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->customer_code,
                    $r->name,
                    $r->address,
                    $r->phone,
                    $r->odc?->name,
                ]);
            }
        });
    }

    protected function exportComplaints($handle): void
    {
        fputcsv($handle, ['Tanggal', 'Pelanggan', 'ODC', 'Problem', 'Action', 'Status']);
        DailyComplaint::orderByDesc('report_date')->chunk(200, function ($rows) use ($handle) {
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->report_date?->toDateString(),
                    $r->customer_name,
                    $r->odc_name,
                    $r->problem,
                    $r->action,
                    $r->status,
                ]);
            }
        });
    }

    protected function exportRouters($handle): void
    {
        fputcsv($handle, ['Nama', 'IP', 'POP', 'Status', 'CPU', 'Memory', 'PPPoE']);
        Router::chunk(200, function ($rows) use ($handle) {
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->name, $r->ip, $r->pop, $r->status,
                    $r->cpu, $r->memory, $r->pppoe_sessions,
                ]);
            }
        });
    }
}
