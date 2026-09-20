<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DailyActivation;
use App\Models\DailyCctvSetup;
use App\Models\DailyComplaint;
use App\Models\DailyDismantle;
use App\Models\DailyNocUpdate;
use App\Models\InternetPackage;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Router;
use App\Services\Audit\ActivityLogger;
use App\Services\DailyEntry\ComplaintHistoryAnalyzer;
use App\Services\DailyEntry\DailyEntryComplaintSerializer;
use App\Services\DailyEntry\DailyEntryRealtimeService;
use App\Services\DailyEntry\DailyEntrySerializer;
use App\Services\Phone\PhoneNormalizer;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DailyEntryController extends Controller
{
    public function __construct(
        private DailyEntryComplaintSerializer $complaintSerializer,
        private DailyEntrySerializer $entrySerializer,
        private DailyEntryRealtimeService $dailyEntryRealtime,
        private ComplaintHistoryAnalyzer $complaintHistoryAnalyzer,
        private ActivityLogger $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $date = $request->string('date', now()->toDateString())->toString();
        $carbon = Carbon::parse($date);
        $with = ['creator:id,name', 'clearer:id,name'];

        $complaints = DailyComplaint::with($with)
            ->where(fn ($q) => $this->forSelectedDateOrStillOpen($q, $carbon))
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get();

        $complaintCountsById = $this->complaintCountsForCustomers(
            $complaints->pluck('customer_id')->filter()->unique()->all(),
            90,
        );
        $complaintCountsByCode = $this->complaintCountsForCodes(
            $complaints->pluck('customer_code')->filter()->unique()->all(),
            90,
        );

        return response()->json([
            'date' => $date,
            'activations' => DailyActivation::with($with)
                ->where(fn ($q) => $this->forSelectedDateOrStillOpen($q, $carbon))
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (DailyActivation $item) => $this->entrySerializer->serialize($item, $date))->values(),
            'cctv_setups' => DailyCctvSetup::with($with)
                ->where(fn ($q) => $this->forSelectedDateOrStillOpen($q, $carbon))
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (DailyCctvSetup $item) => $this->entrySerializer->serialize($item, $date))->values(),
            'dismantles' => DailyDismantle::with($with)
                ->where(fn ($q) => $this->forSelectedDateOrStillOpen($q, $carbon))
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (DailyDismantle $item) => $this->entrySerializer->serialize($item, $date))->values(),
            'complaints' => $complaints->map(function (DailyComplaint $complaint) use ($date, $complaintCountsById, $complaintCountsByCode) {
                $row = $this->complaintSerializer->serialize($complaint, $date);
                if ($complaint->customer_id && isset($complaintCountsById[$complaint->customer_id])) {
                    $row['complaint_count_90d'] = (int) $complaintCountsById[$complaint->customer_id];
                } elseif ($complaint->customer_code && isset($complaintCountsByCode[$complaint->customer_code])) {
                    $row['complaint_count_90d'] = (int) $complaintCountsByCode[$complaint->customer_code];
                }

                return $row;
            })->values(),
            'noc_updates' => DailyNocUpdate::with($with)
                ->where(fn ($q) => $this->forSelectedDateOrStillOpen($q, $carbon))
                ->orderByDesc('report_date')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (DailyNocUpdate $item) => $this->entrySerializer->serialize($item, $date))->values(),
            'lookups' => $this->lookups(),
            'status_options' => ReportStatus::options(),
            'summary' => [
                'activations' => DailyActivation::whereDate('report_date', $carbon)->count(),
                'complaints' => DailyComplaint::whereDate('report_date', $carbon)->count(),
                'dismantles' => DailyDismantle::whereDate('report_date', $carbon)->count(),
            ],
        ]);
    }

    /**
     * Item tanggal terpilih + item open dari tanggal sebelumnya (belum Clear)
     * + item yang di-clear pada tanggal terpilih (meski report_date lebih lama).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function forSelectedDateOrStillOpen($query, Carbon $date): void
    {
        $day = $date->toDateString();

        $query->where(function ($q) use ($date, $day) {
            $q->whereDate('report_date', $date)
                ->orWhere(function ($q2) use ($day) {
                    $q2->whereDate('report_date', '<', $day)
                        ->where(function ($q3) {
                            $q3->whereNull('status')
                                ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
                        });
                })
                ->orWhere(function ($q2) use ($date) {
                    $q2->whereNotNull('cleared_at')
                        ->whereBetween('cleared_at', [
                            $date->copy()->startOfDay(),
                            $date->copy()->endOfDay(),
                        ]);
                });
        });
    }

    /**
     * Item dalam rentang tanggal + item open dari sebelum rentang (belum Clear)
     * + item yang di-clear dalam rentang (meski report_date lebih lama).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function forDateRangeOrStillOpen($query, string $from, string $to): void
    {
        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();

        $query->where(function ($q) use ($from, $to, $fromStart, $toEnd) {
            $q->whereBetween('report_date', [$from, $to])
                ->orWhere(function ($q2) use ($from) {
                    $q2->whereDate('report_date', '<', $from)
                        ->where(function ($q3) {
                            $q3->whereNull('status')
                                ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
                        });
                })
                ->orWhere(function ($q2) use ($fromStart, $toEnd) {
                    $q2->whereNotNull('cleared_at')
                        ->whereBetween('cleared_at', [$fromStart, $toEnd]);
                });
        });
    }

    public function complaintHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        $days = (int) ($data['days'] ?? 90);
        $query = DailyComplaint::query()
            ->where('complaint_type', DailyComplaint::TYPE_INDIVIDUAL)
            ->orderByDesc('report_date')
            ->orderByDesc('id');

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->find($data['customer_id']);
            $query->where(function ($q) use ($data, $customer) {
                $q->where('customer_id', $data['customer_id']);
                if ($customer?->customer_code) {
                    $q->orWhere('customer_code', $customer->customer_code);
                }
            });
        } elseif (! empty($data['customer_code'])) {
            $code = trim($data['customer_code']);
            $query->where('customer_code', $code);
        } elseif (! empty($data['phone'])) {
            $local = PhoneNormalizer::toLocal($data['phone']) ?: $data['phone'];
            $query->where(function ($q) use ($local, $data) {
                $q->where('phone_normalized', $local)
                    ->orWhere('phone_normalized', $data['phone']);
            });
        } elseif (! empty($data['name'])) {
            $name = preg_replace('/\s*\(.*\)\s*$/', '', trim($data['name'])) ?: trim($data['name']);
            $query->where('customer_name', 'like', $name.'%');
        } else {
            return response()->json([
                'total' => 0,
                'days' => $days,
                'items' => [],
                'summary' => $this->complaintHistoryAnalyzer->summarize(collect(), $days, 0),
            ]);
        }

        if ($days > 0) {
            $query->whereDate('report_date', '>=', now()->subDays($days)->toDateString());
        }

        if (! empty($data['exclude_id'])) {
            $query->where('id', '!=', $data['exclude_id']);
        }

        $total = (clone $query)->count();
        $items = (clone $query)
            ->with(['creator:id,name'])
            ->limit(50)
            ->get();

        $summary = $this->complaintHistoryAnalyzer->summarize($items, $days, $total);

        return response()->json([
            'total' => $total,
            'days' => $days,
            'summary' => $summary,
            'items' => $items->map(fn (DailyComplaint $c) => [
                'id' => $c->id,
                'report_date' => $c->report_date?->toDateString(),
                'problem' => $c->problem,
                'status' => $c->status,
                'odc_name' => $c->odc_name,
                'shift' => $c->shift,
                'action' => $c->action,
                'customer_name' => $c->customer_name,
                'creator_name' => $c->creator?->name,
                'cleared_at' => $c->cleared_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $phoneVariants = PhoneNormalizer::searchVariants($q);

        $customers = Customer::query()
            ->with('odc:id,name,code')
            ->where(function ($query) use ($q, $phoneVariants) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('customer_code', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('odc', fn ($odc) => $odc->where('name', 'like', "%{$q}%"));

                foreach ($phoneVariants as $variant) {
                    if ($variant !== $q) {
                        $query->orWhere('phone', $variant)
                            ->orWhere('phone', 'like', "%{$variant}%");
                    }
                }
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'customer_code', 'address', 'phone', 'odc_id']);

        return response()->json($customers);
    }

    public function storeActivation(Request $request): JsonResponse
    {
        $data = $this->validateActivation($request);
        $item = new DailyActivation([...$data, 'created_by' => $request->user()->id]);
        $item->syncClearAttribution($data['status'], $request->user()->id);
        $item->save();
        $item->load(['creator:id,name', 'clearer:id,name']);
        $this->activity->log('activation', "Tambah aktivasi {$item->customer_name}", $request->user(), $request, $item);

        return response()->json(['message' => 'Aktivasi berhasil ditambahkan.', 'data' => $this->entrySerializer->serialize($item)], 201);
    }

    public function updateActivation(Request $request, DailyActivation $dailyActivation): JsonResponse
    {
        $data = $this->validateActivation($request);
        $previous = $dailyActivation->status;
        $dailyActivation->fill($data);
        $dailyActivation->syncClearAttribution($data['status'], $request->user()->id, $previous);
        $dailyActivation->save();
        $dailyActivation->load(['creator:id,name', 'clearer:id,name']);
        $this->activity->log('activation', "Edit aktivasi {$dailyActivation->customer_name}", $request->user(), $request, $dailyActivation);

        return response()->json(['message' => 'Aktivasi berhasil diperbarui.', 'data' => $this->entrySerializer->serialize($dailyActivation)]);
    }

    public function storeCctv(Request $request): JsonResponse
    {
        $data = $this->validateCctv($request);
        $item = new DailyCctvSetup([...$data, 'created_by' => $request->user()->id]);
        $item->syncClearAttribution($data['status'], $request->user()->id);
        $item->save();
        $item->load(['creator:id,name', 'clearer:id,name']);
        $this->activity->log('cctv', "Tambah CCTV {$item->customer_name}", $request->user(), $request, $item);

        return response()->json(['message' => 'Data CCTV berhasil ditambahkan.', 'data' => $this->entrySerializer->serialize($item)], 201);
    }

    public function updateCctv(Request $request, DailyCctvSetup $dailyCctv): JsonResponse
    {
        $data = $this->validateCctv($request);
        $previous = $dailyCctv->status;
        $dailyCctv->fill($data);
        $dailyCctv->syncClearAttribution($data['status'], $request->user()->id, $previous);
        $dailyCctv->save();
        $dailyCctv->load(['creator:id,name', 'clearer:id,name']);
        $this->activity->log('cctv', "Edit CCTV {$dailyCctv->customer_name}", $request->user(), $request, $dailyCctv);

        return response()->json(['message' => 'Data CCTV berhasil diperbarui.', 'data' => $this->entrySerializer->serialize($dailyCctv)]);
    }

    public function storeDismantle(Request $request): JsonResponse
    {
        $data = $this->validateDismantle($request);
        if ($data['status'] === ReportStatus::CLEAR && empty($data['close_ticket'])) {
            $data['close_ticket'] = today()->toDateString();
        }
        $item = new DailyDismantle([...$data, 'created_by' => $request->user()->id]);
        $item->syncClearAttribution($data['status'], $request->user()->id);
        $item->save();
        $item->load(['creator:id,name', 'clearer:id,name']);
        $label = $item->customer_name ?: ($item->customer_code ?: '#'.$item->id);
        $this->activity->log('dismantle', "Tambah dismantle {$label}", $request->user(), $request, $item);

        return response()->json(['message' => 'Dismantle berhasil ditambahkan.', 'data' => $this->entrySerializer->serialize($item)], 201);
    }

    public function updateDismantle(Request $request, DailyDismantle $dailyDismantle): JsonResponse
    {
        $data = $this->validateDismantle($request);
        if ($data['status'] === ReportStatus::CLEAR && empty($data['close_ticket'])) {
            $data['close_ticket'] = today()->toDateString();
        }
        $previous = $dailyDismantle->status;
        $dailyDismantle->fill($data);
        $dailyDismantle->syncClearAttribution($data['status'], $request->user()->id, $previous);
        $dailyDismantle->save();
        $dailyDismantle->load(['creator:id,name', 'clearer:id,name']);
        $label = $dailyDismantle->customer_name ?: ($dailyDismantle->customer_code ?: '#'.$dailyDismantle->id);
        $this->activity->log('dismantle', "Edit dismantle {$label}", $request->user(), $request, $dailyDismantle);

        return response()->json(['message' => 'Dismantle berhasil diperbarui.', 'data' => $this->entrySerializer->serialize($dailyDismantle)]);
    }

    public function storeComplaint(Request $request): JsonResponse
    {
        $data = $this->validateComplaint($request);
        if ($data['status'] === ReportStatus::CLEAR) {
            $this->assertComplaintActionForClear($data['action'] ?? null);
            if (empty($data['end_problem'])) {
                $data['end_problem'] = today()->toDateString();
            }
        }
        $item = new DailyComplaint([...$data, 'created_by' => $request->user()->id]);
        $item->syncClearAttribution($data['status'], $request->user()->id);
        $item->save();
        $item->load(['creator:id,name', 'clearer:id,name']);
        $this->dailyEntryRealtime->complaintCreated($item);
        $this->activity->log(
            'complaint',
            'Tambah komplain '.$item->displayLabel(),
            $request->user(),
            $request,
            $item,
        );

        return response()->json([
            'message' => 'Komplain berhasil ditambahkan.',
            'data' => $this->complaintSerializer->serialize($item, $data['report_date']),
        ], 201);
    }

    public function updateComplaint(Request $request, DailyComplaint $dailyComplaint): JsonResponse
    {
        $data = $this->validateComplaint($request);
        if ($data['status'] === ReportStatus::CLEAR && empty($data['end_problem'])) {
            $data['end_problem'] = today()->toDateString();
        }
        if ($data['status'] === ReportStatus::CLEAR) {
            $this->assertComplaintActionForClear($data['action'] ?? null);
        }
        $previous = $dailyComplaint->status;
        $dailyComplaint->fill($data);
        $dailyComplaint->syncClearAttribution($data['status'], $request->user()->id, $previous);
        $dailyComplaint->save();
        $fresh = $dailyComplaint->fresh(['creator:id,name', 'clearer:id,name']);
        $this->dailyEntryRealtime->complaintUpdated($fresh);
        $this->activity->log(
            'complaint',
            'Edit komplain '.$fresh->displayLabel(),
            $request->user(),
            $request,
            $fresh,
        );

        return response()->json([
            'message' => 'Komplain berhasil diperbarui.',
            'data' => $this->complaintSerializer->serialize($fresh, $data['report_date']),
        ]);
    }

    public function storeNocUpdate(Request $request): JsonResponse
    {
        $data = $this->validateNocUpdate($request);
        $item = new DailyNocUpdate([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $request->user()->id,
        ]);
        $item->syncClearAttribution($data['status'], $request->user()->id);
        $item->save();
        $item->load(['creator:id,name', 'clearer:id,name']);
        $this->activity->log('noc', 'Tambah update NOC', $request->user(), $request, $item);

        return response()->json(['message' => 'Update NOC berhasil ditambahkan.', 'data' => $this->entrySerializer->serialize($item)], 201);
    }

    public function updateNocUpdate(Request $request, DailyNocUpdate $dailyNocUpdate): JsonResponse
    {
        $data = $this->validateNocUpdate($request);
        $previous = $dailyNocUpdate->status;
        $dailyNocUpdate->fill([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $dailyNocUpdate->syncClearAttribution($data['status'], $request->user()->id, $previous);
        $dailyNocUpdate->save();
        $dailyNocUpdate->load(['creator:id,name', 'clearer:id,name']);
        $this->activity->log('noc', 'Edit update NOC', $request->user(), $request, $dailyNocUpdate);

        return response()->json(['message' => 'Update NOC berhasil diperbarui.', 'data' => $this->entrySerializer->serialize($dailyNocUpdate)]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $model = $this->resolveModel($type)::findOrFail($id);
        $label = $this->activityLabelFor($type, $model);

        if ($type === 'complaint' && $model instanceof DailyComplaint) {
            $reportDate = $model->report_date?->toDateString() ?? today()->toDateString();
            $snapshot = $this->complaintSerializer->serialize($model, $reportDate);
            $model->delete();
            $this->dailyEntryRealtime->complaintDeleted($id, $reportDate, $snapshot);
            $this->activity->log($this->activityTypeFor($type), "Hapus {$label}", request()->user(), request());

            return response()->json(['message' => 'Data berhasil dihapus.']);
        }

        $model->delete();
        $this->activity->log($this->activityTypeFor($type), "Hapus {$label}", request()->user(), request());

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    public function updateStatus(Request $request, string $type, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:On-Progress,Clear',
        ]);

        $model = $this->resolveModel($type)::findOrFail($id);
        $previous = $model->status;

        if ($data['status'] === ReportStatus::CLEAR) {
            if ($type === 'dismantle' && ! $model->close_ticket) {
                $model->close_ticket = today();
            }
            if ($type === 'complaint') {
                $this->assertComplaintActionForClear($model->action);
                if (! $model->end_problem) {
                    $model->end_problem = today();
                }
            }
        }

        $model->status = $data['status'];
        $model->syncClearAttribution($data['status'], $request->user()?->id, $previous);
        $model->save();
        $fresh = $model->fresh(['creator:id,name', 'clearer:id,name']);

        if ($type === 'complaint' && $fresh instanceof DailyComplaint) {
            $this->dailyEntryRealtime->complaintUpdated($fresh);
        }

        $responseData = ($type === 'complaint' && $fresh instanceof DailyComplaint)
            ? $this->complaintSerializer->serialize($fresh, $fresh->report_date?->toDateString())
            : $this->entrySerializer->serialize($fresh);

        $this->activity->log(
            $this->activityTypeFor($type),
            "Ubah status {$this->activityLabelFor($type, $fresh)} → {$data['status']}",
            $request->user(),
            $request,
            $fresh,
        );

        return response()->json([
            'message' => 'Status berhasil diperbarui menjadi '.$data['status'].'.',
            'data' => $responseData,
        ]);
    }

    protected function lookups(): array
    {
        return [
            'olts' => Olt::query()
                ->with('pop:id,name')
                ->orderBy('name')
                ->get(['id', 'pop_id', 'name', 'ip'])
                ->map(fn (Olt $olt) => [
                    'id' => $olt->id,
                    'pop_id' => $olt->pop_id,
                    'pop_name' => $olt->pop?->name,
                    'name' => $olt->name,
                    'ip' => $olt->ip,
                ])
                ->values(),
            'sites' => Customer::whereNotNull('area')->where('area', '!=', '')->distinct()->orderBy('area')->pluck('area'),
            'odcs' => Odc::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Odc $odc) => [
                    'id' => $odc->id,
                    'name' => $odc->name,
                    'code' => $odc->code,
                ])
                ->values(),
            'odps' => Odp::query()
                ->with('odc:id,name')
                ->orderBy('name')
                ->get(['id', 'odc_id', 'name', 'code'])
                ->map(fn (Odp $odp) => [
                    'id' => $odp->id,
                    'odc_id' => $odp->odc_id,
                    'odc_name' => $odp->odc?->name,
                    'name' => $odp->name,
                    'code' => $odp->code,
                ])
                ->values(),
            'routers' => Router::orderBy('name')->pluck('name'),
            'packages' => InternetPackage::query()
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'active');
                })
                ->orderBy('name')
                ->get(['id', 'name', 'speed_mbps'])
                ->map(fn (InternetPackage $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'speed_mbps' => $p->speed_mbps,
                ])
                ->values(),
        ];
    }

    protected function resolveModel(string $type): string
    {
        return match ($type) {
            'activation' => DailyActivation::class,
            'cctv' => DailyCctvSetup::class,
            'dismantle' => DailyDismantle::class,
            'complaint' => DailyComplaint::class,
            'noc-update' => DailyNocUpdate::class,
            default => abort(404),
        };
    }

    protected function activityTypeFor(string $type): string
    {
        return match ($type) {
            'activation' => 'activation',
            'cctv' => 'cctv',
            'dismantle' => 'dismantle',
            'complaint' => 'complaint',
            'noc-update' => 'noc',
            default => $type,
        };
    }

    protected function activityLabelFor(string $type, mixed $model): string
    {
        if ($type === 'complaint' && $model instanceof DailyComplaint) {
            return 'komplain '.$model->displayLabel();
        }

        $name = is_object($model) ? (string) ($model->customer_name ?? '') : '';
        $kind = match ($type) {
            'activation' => 'aktivasi',
            'cctv' => 'CCTV',
            'dismantle' => 'dismantle',
            'noc-update' => 'update NOC',
            default => $type,
        };

        return trim($kind.' '.$name) ?: $kind;
    }

    protected function validateActivation(Request $request): array
    {
        return $request->validate([
            'report_date' => 'required|date',
            'customer_name' => 'required|string|max:255',
            'package_name' => 'nullable|string|max:255',
            'olt_name' => 'nullable|string|max:255',
            'odp_name' => 'nullable|string|max:255',
            'port_onu' => 'nullable|string|max:100',
            'status' => 'required|in:On-Progress,Clear',
            'notes' => 'nullable|string',
        ]);
    }

    protected function validateCctv(Request $request): array
    {
        $data = $request->validate([
            'report_date' => 'required|date',
            'customer_name' => 'nullable|string|max:255',
            'router' => 'nullable|string|max:255',
            'status' => 'nullable|in:On-Progress,Clear',
        ]);
        $data['status'] = $data['status'] ?? ReportStatus::ON_PROGRESS;

        return $data;
    }

    protected function validateDismantle(Request $request): array
    {
        return $request->validate([
            'report_date' => 'required|date',
            'customer_name' => 'required|string|max:255',
            'customer_code' => 'nullable|string|max:100',
            'site_name' => 'nullable|string|max:255',
            'start_ticket' => 'nullable|date',
            'close_ticket' => 'nullable|date',
            'status' => 'required|in:Pending,On-Progress,Clear',
        ]);
    }

    protected function validateComplaint(Request $request): array
    {
        $request->merge([
            'start_problem' => $request->filled('start_problem') ? $request->input('start_problem') : null,
            'end_problem' => $request->filled('end_problem') ? $request->input('end_problem') : null,
            'complaint_type' => $request->input('complaint_type', DailyComplaint::TYPE_INDIVIDUAL),
        ]);

        $type = $request->string('complaint_type')->toString();

        $rules = [
            'report_date' => 'required|date',
            'complaint_type' => 'required|in:individual,gamas',
            'odc_name' => 'nullable|string|max:255',
            'phone_normalized' => 'nullable|string|max:30',
            'start_problem' => 'nullable|date',
            'end_problem' => 'nullable|date',
            'problem' => 'nullable|string|max:255',
            'action' => 'nullable|string',
            'status' => 'required|in:On-Progress,Clear',
            'shift' => 'nullable|integer|in:1,2,3',
        ];

        if ($type === DailyComplaint::TYPE_GAMAS) {
            $rules = [
                ...$rules,
                'customer_id' => 'nullable',
                'customer_code' => 'nullable|string|max:100',
                'customer_name' => 'nullable|string|max:255',
                'gamas_kind' => 'required|in:odp,upstream,olt,other',
                'location_label' => 'required|string|max:255',
                'impact' => 'nullable|string|max:100',
            ];
        } else {
            $rules = [
                ...$rules,
                'customer_id' => 'nullable|integer|exists:customers,id',
                'customer_code' => 'required|string|max:100',
                'customer_name' => 'required|string|max:255',
                'gamas_kind' => 'nullable',
                'location_label' => 'nullable|string|max:255',
                'impact' => 'nullable|string|max:100',
            ];
        }

        $data = $request->validate($rules);

        $phone = trim((string) ($data['phone_normalized'] ?? ''));
        $data['phone_normalized'] = $phone !== '' ? (PhoneNormalizer::toLocal($phone) ?: $phone) : null;

        if ($data['complaint_type'] === DailyComplaint::TYPE_GAMAS) {
            $data['customer_id'] = null;
            $data['customer_code'] = null;
            $data['phone_normalized'] = null;
            $location = trim((string) $data['location_label']);
            $impact = trim((string) ($data['impact'] ?? ''));
            $data['customer_name'] = $impact !== ''
                ? "{$location} ({$impact})"
                : $location;
        } else {
            $data['gamas_kind'] = null;
            $data['location_label'] = null;
            $data['impact'] = null;

            $code = trim((string) ($data['customer_code'] ?? ''));
            $data['customer_code'] = $code;

            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::query()->with('odc:id,name')->find($data['customer_id']);
            }
            if (! $customer && $code !== '') {
                $customer = Customer::query()->with('odc:id,name')
                    ->where('customer_code', $code)
                    ->first();
            }

            if ($customer) {
                $data['customer_id'] = $customer->id;
                $data['customer_code'] = $customer->customer_code;
                if (trim((string) ($data['customer_name'] ?? '')) === '') {
                    $data['customer_name'] = $customer->name;
                }
                if (empty($data['odc_name']) && $customer->odc) {
                    $data['odc_name'] = $customer->odc->name;
                } elseif (empty($data['odc_name']) && $customer->odc_id) {
                    $data['odc_name'] = Odc::query()->whereKey($customer->odc_id)->value('name');
                }
                if (empty($data['phone_normalized']) && $customer->phone) {
                    $data['phone_normalized'] = PhoneNormalizer::toLocal($customer->phone) ?: $customer->phone;
                }
            } else {
                $data['customer_id'] = null;
            }
        }

        return $data;
    }

    /** @param  array<int, int|string>  $customerIds
     *  @return array<int|string, int>
     */
    protected function complaintCountsForCustomers(array $customerIds, int $days = 90): array
    {
        if ($customerIds === []) {
            return [];
        }

        return DailyComplaint::query()
            ->whereIn('customer_id', $customerIds)
            ->where('complaint_type', DailyComplaint::TYPE_INDIVIDUAL)
            ->whereDate('report_date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->all();
    }

    /** @param  array<int, string>  $codes
     *  @return array<string, int>
     */
    protected function complaintCountsForCodes(array $codes, int $days = 90): array
    {
        $codes = array_values(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            $codes,
        )));
        if ($codes === []) {
            return [];
        }

        return DailyComplaint::query()
            ->whereIn('customer_code', $codes)
            ->where('complaint_type', DailyComplaint::TYPE_INDIVIDUAL)
            ->whereDate('report_date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('customer_code, COUNT(*) as total')
            ->groupBy('customer_code')
            ->pluck('total', 'customer_code')
            ->all();
    }

    protected function validateNocUpdate(Request $request): array
    {
        return $request->validate([
            'report_date' => 'required|date',
            'description' => 'required|string',
            'odc_name' => 'nullable|string|max:255',
            'status' => 'required|in:On-Progress,Clear',
            'sort_order' => 'nullable|integer',
        ]);
    }

    public function exportComplaints(Request $request)
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $search = trim($request->string('search')->toString());
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $rows = DailyComplaint::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('problem', 'like', "%{$search}%")
                        ->orWhere('location_label', 'like', "%{$search}%");
                });
            }, function ($q) use ($from, $to, $odc, $unmapped) {
                $q->whereBetween('report_date', [$from, $to]);
                if ($unmapped) {
                    $this->scopeEmptyOdcName($q);
                } elseif ($odc) {
                    $q->where('odc_name', $odc);
                }
            })
            ->orderBy('report_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DailyComplaint $r) => [
                $r->report_date?->toDateString(),
                $r->complaint_type,
                $r->customer_code,
                $r->customer_name,
                $r->odc_name,
                $r->location_label,
                $r->problem,
                $r->action,
                $r->shift,
                $r->status,
                $r->start_problem?->toDateString(),
                $r->end_problem?->toDateString(),
                $r->creator?->name,
                $r->clearer?->name,
            ]);

        return \App\Support\ExcelExport::download(
            'komplain-'.$from.'-'.$to.'.xlsx',
            ['Tanggal', 'Tipe', 'Kode', 'Nama', 'ODC/Site', 'Lokasi', 'Problem', 'Action', 'Shift', 'Status', 'Start Problem', 'End Problem', 'Input oleh', 'Clear oleh'],
            $rows,
        );
    }

    public function listComplaints(Request $request): JsonResponse
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $search = trim($request->string('search')->toString());
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $items = DailyComplaint::query()
            ->with(['creator:id,name', 'clearer:id,name', 'customer:id,name,customer_code,odc_id'])
            ->when($search !== '', function ($q) use ($search) {
                // Search global: abaikan filter periode & ODC
                $q->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('problem', 'like', "%{$search}%")
                        ->orWhere('location_label', 'like', "%{$search}%");
                });
            }, function ($q) use ($from, $to, $odc, $unmapped) {
                $q->where(fn ($q2) => $this->forDateRangeOrStillOpen($q2, $from, $to));
                if ($unmapped) {
                    $this->scopeEmptyOdcName($q);
                } elseif ($odc) {
                    $q->where('odc_name', $odc);
                }
            })
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (DailyComplaint $c) => $this->complaintSerializer->serialize($c, $from));

        return response()->json(['data' => $items]);
    }

    public function listNocUpdates(Request $request): JsonResponse
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $items = DailyNocUpdate::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->where(fn ($q) => $this->forDateRangeOrStillOpen($q, $from, $to))
            ->when($unmapped, fn ($q) => $this->scopeEmptyOdcName($q))
            ->when(! $unmapped && $odc, fn ($q) => $q->where('odc_name', $odc))
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (DailyNocUpdate $n) => $this->entrySerializer->serialize($n, $to));

        return response()->json(['data' => $items]);
    }

    public function listActivations(Request $request): JsonResponse
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $search = trim($request->string('search')->toString());
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $oltNames = (! $unmapped && $odc) ? $this->oltNamesForOdc($odc) : [];
        $odpNames = (! $unmapped && $odc) ? $this->odpNamesForOdc($odc) : [];

        $items = DailyActivation::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->where(fn ($q) => $this->forDateRangeOrStillOpen($q, $from, $to))
            ->when($search !== '', fn ($q) => $q->where('customer_name', 'like', "%{$search}%"))
            ->when($unmapped, fn ($q) => $this->scopeActivationUnmapped($q))
            ->when(! $unmapped && $odc, function ($q) use ($odc, $oltNames, $odpNames) {
                $q->where(function ($q2) use ($odc, $oltNames, $odpNames) {
                    if ($oltNames !== []) {
                        $q2->orWhereIn('olt_name', $oltNames);
                    }
                    if ($odpNames !== []) {
                        $q2->orWhereIn('odp_name', $odpNames);
                    }
                    $q2->orWhereRaw('LOWER(TRIM(olt_name)) = ?', [mb_strtolower($odc)])
                        ->orWhereRaw('LOWER(TRIM(odp_name)) = ?', [mb_strtolower($odc)]);
                });
            })
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (DailyActivation $a) => $this->entrySerializer->serialize($a, $to));

        return response()->json(['data' => $items]);
    }

    public function listCctvSetups(Request $request): JsonResponse
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $search = trim($request->string('search')->toString());
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $customerNames = (! $unmapped && $odc) ? $this->customerNamesForOdc($odc) : [];

        $items = DailyCctvSetup::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->where(fn ($q) => $this->forDateRangeOrStillOpen($q, $from, $to))
            ->when($search !== '', fn ($q) => $q->where('customer_name', 'like', "%{$search}%"))
            ->when($unmapped, fn ($q) => $this->scopeCctvUnmapped($q))
            ->when(! $unmapped && $odc && $customerNames !== [], fn ($q) => $q->whereIn('customer_name', $customerNames))
            ->when(! $unmapped && $odc && $customerNames === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (DailyCctvSetup $c) => $this->entrySerializer->serialize($c, $to));

        return response()->json(['data' => $items]);
    }

    protected function isUnmappedOdcFilter(?string $odc): bool
    {
        if ($odc === null || $odc === '') {
            return false;
        }

        $key = mb_strtolower(trim($odc));

        return $key === '__none__' || $key === 'tanpa odc';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function scopeEmptyOdcName($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('odc_name')
                ->orWhere('odc_name', '')
                ->orWhereRaw("TRIM(odc_name) = ''");
        });
    }

    /**
     * Aktivasi tanpa map OLT→ODC maupun ODP→ODC.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyActivation>  $query
     */
    protected function scopeActivationUnmapped($query): void
    {
        $mappedOlts = Olt::query()
            ->whereNotNull('odc_id')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $mappedOdps = Odp::query()
            ->whereNotNull('odc_id')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query->where(function ($q) use ($mappedOlts, $mappedOdps) {
            $q->where(function ($qOlt) use ($mappedOlts) {
                $qOlt->whereNull('olt_name')->orWhere('olt_name', '')->orWhereRaw("TRIM(olt_name) = ''");
                if ($mappedOlts !== []) {
                    $ph = implode(',', array_fill(0, count($mappedOlts), '?'));
                    $qOlt->orWhereRaw("LOWER(TRIM(olt_name)) NOT IN ($ph)", $mappedOlts);
                }
            })->where(function ($qOdp) use ($mappedOdps) {
                $qOdp->whereNull('odp_name')->orWhere('odp_name', '')->orWhereRaw("TRIM(odp_name) = ''");
                if ($mappedOdps !== []) {
                    $ph = implode(',', array_fill(0, count($mappedOdps), '?'));
                    $qOdp->orWhereRaw("LOWER(TRIM(odp_name)) NOT IN ($ph)", $mappedOdps);
                }
            });
        });
    }

    /**
     * CCTV yang customer-nya belum punya ODC di master pelanggan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyCctvSetup>  $query
     */
    protected function scopeCctvUnmapped($query): void
    {
        $mappedCustomers = Customer::query()
            ->whereNotNull('odc_id')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query->where(function ($q) use ($mappedCustomers) {
            $q->whereNull('customer_name')->orWhere('customer_name', '')->orWhereRaw("TRIM(customer_name) = ''");
            if ($mappedCustomers !== []) {
                $ph = implode(',', array_fill(0, count($mappedCustomers), '?'));
                $q->orWhereRaw("LOWER(TRIM(customer_name)) NOT IN ($ph)", $mappedCustomers);
            }
        });
    }

    /** @return list<string> */
    protected function oltNamesForOdc(string $odcName): array
    {
        return Olt::query()
            ->whereHas('odc', fn ($q) => $q->where('name', $odcName))
            ->pluck('name')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /** @return list<string> */
    protected function odpNamesForOdc(string $odcName): array
    {
        return Odp::query()
            ->whereHas('odc', fn ($q) => $q->where('name', $odcName))
            ->pluck('name')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /** @return list<string> */
    protected function customerNamesForOdc(string $odcName): array
    {
        return Customer::query()
            ->whereHas('odc', fn ($q) => $q->where('name', $odcName))
            ->pluck('name')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    public function exportNocUpdates(Request $request)
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $rows = DailyNocUpdate::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->whereBetween('report_date', [$from, $to])
            ->when($unmapped, fn ($q) => $this->scopeEmptyOdcName($q))
            ->when(! $unmapped && $odc, fn ($q) => $q->where('odc_name', $odc))
            ->orderBy('report_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DailyNocUpdate $r) => [
                $r->report_date?->toDateString(),
                $r->odc_name,
                $r->description,
                $r->status,
                $r->creator?->name,
                $r->clearer?->name,
            ]);

        return \App\Support\ExcelExport::download(
            'update-noc-'.$from.'-'.$to.'.xlsx',
            ['Tanggal', 'ODC/Site', 'Deskripsi', 'Status', 'Input oleh', 'Clear oleh'],
            $rows,
        );
    }

    protected function assertComplaintActionForClear(?string $action): void
    {
        if (trim((string) $action) === '') {
            throw ValidationException::withMessages([
                'action' => 'Action/perbaikan wajib diisi sebelum status Clear.',
            ]);
        }
    }
}
