<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGamas;
use App\Models\DailyGamasUpdate;
use App\Services\Audit\ActivityLogger;
use App\Services\DailyEntry\DailyEntryRealtimeService;
use App\Services\DailyEntry\DailyEntrySerializer;
use App\Services\Gamas\GamasStatsService;
use App\Support\ExcelExport;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GamasController extends Controller
{
    public function __construct(
        private DailyEntrySerializer $serializer,
        private DailyEntryRealtimeService $realtime,
        private ActivityLogger $activity,
        private GamasStatsService $statsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $search = trim($request->string('search')->toString());
        $status = trim($request->string('status')->toString());
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $items = DailyGamas::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->when($search !== '', function ($q) use ($search) {
                // Search global: abaikan filter periode, status & ODC
                $q->where(function ($inner) use ($search) {
                    $inner->where('location_label', 'like', "%{$search}%")
                        ->orWhere('impact', 'like', "%{$search}%")
                        ->orWhere('problem', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            }, function ($q) use ($from, $to, $odc, $unmapped, $request, $status) {
                $q->where(fn ($q2) => $this->applyListDateFilter($q2, $request, $from, $to));
                if ($status !== '' && $status !== 'all' && ! $this->isClearMode($request)) {
                    $q->where('status', $status);
                }
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
            ->map(fn (DailyGamas $g) => $this->serializer->serialize($g, $from));

        return response()->json(['data' => $items]);
    }

    public function stats(Request $request): JsonResponse
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();

        return response()->json($this->statsService->forRange($from, $to));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertActionForClear($data);
        if ($data['status'] === ReportStatus::CLEAR && empty($data['end_problem'])) {
            $data['end_problem'] = today()->toDateString();
        }

        $gamas = new DailyGamas([...$data, 'created_by' => $request->user()->id]);
        $gamas->syncClearAttribution($data['status'], $request->user()->id);
        $gamas->save();
        $gamas->load(['creator:id,name', 'clearer:id,name']);

        $this->realtime->gamasCreated($gamas);
        $this->activity->log(
            'gamas',
            'Tambah gamas '.$gamas->displayLabel(),
            $request->user(),
            $request,
            $gamas,
        );

        return response()->json([
            'message' => 'Gamas berhasil ditambahkan.',
            'data' => $this->serializer->serialize($gamas, $data['report_date']),
        ], 201);
    }

    public function update(Request $request, DailyGamas $dailyGamas): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertActionForClear($data);
        if ($data['status'] === ReportStatus::CLEAR && empty($data['end_problem'])) {
            $data['end_problem'] = today()->toDateString();
        }

        $previous = $dailyGamas->status;
        $dailyGamas->fill($data);
        $dailyGamas->syncClearAttribution($data['status'], $request->user()->id, $previous);
        $dailyGamas->save();

        $fresh = $dailyGamas->fresh(['creator:id,name', 'clearer:id,name']);
        $this->realtime->gamasUpdated($fresh);
        $this->activity->log(
            'gamas',
            'Edit gamas '.$fresh->displayLabel(),
            $request->user(),
            $request,
            $fresh,
        );

        return response()->json([
            'message' => 'Gamas berhasil diperbarui.',
            'data' => $this->serializer->serialize($fresh, $data['report_date']),
        ]);
    }

    public function destroy(DailyGamas $dailyGamas): JsonResponse
    {
        $reportDate = $dailyGamas->report_date?->toDateString() ?? today()->toDateString();
        $snapshot = $this->serializer->serialize($dailyGamas, $reportDate);
        $id = $dailyGamas->getKey();
        $label = $dailyGamas->displayLabel();
        $dailyGamas->delete();

        $this->realtime->gamasDeleted($id, $reportDate, $snapshot);
        $this->activity->log('gamas', "Hapus gamas {$label}", request()->user(), request());

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    public function updateStatus(Request $request, DailyGamas $dailyGamas): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', ReportStatus::options()),
        ]);

        $previous = $dailyGamas->status;

        if ($data['status'] === ReportStatus::CLEAR) {
            $this->assertActionForClear(['action' => $dailyGamas->action, 'status' => ReportStatus::CLEAR]);
            if (! $dailyGamas->end_problem) {
                $dailyGamas->end_problem = today();
            }
        }

        $dailyGamas->status = $data['status'];
        $dailyGamas->syncClearAttribution($data['status'], $request->user()?->id, $previous);
        $dailyGamas->save();

        $fresh = $dailyGamas->fresh(['creator:id,name', 'clearer:id,name']);
        $this->realtime->gamasUpdated($fresh);
        $this->activity->log(
            'gamas',
            "Ubah status gamas {$fresh->displayLabel()} → {$data['status']}",
            $request->user(),
            $request,
            $fresh,
        );

        return response()->json([
            'message' => 'Status berhasil diperbarui menjadi '.$data['status'].'.',
            'data' => $this->serializer->serialize($fresh, $fresh->report_date?->toDateString()),
        ]);
    }

    public function updates(DailyGamas $dailyGamas): JsonResponse
    {
        $items = $dailyGamas->updates()
            ->with('author:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DailyGamasUpdate $update) => $this->serializeUpdate($update));

        return response()->json(['data' => $items]);
    }

    public function storeUpdate(Request $request, DailyGamas $dailyGamas): JsonResponse
    {
        $request->merge([
            'update_text' => trim((string) $request->input('update_text')),
        ]);
        $data = $request->validate([
            'update_text' => 'required|string|max:2000',
        ]);

        $update = $dailyGamas->updates()->create([
            'update_text' => $data['update_text'],
            'created_by' => $request->user()?->id,
        ]);
        $update->load('author:id,name');

        $fresh = $dailyGamas->fresh(['creator:id,name', 'clearer:id,name']);
        $this->realtime->gamasUpdated($fresh);
        $this->activity->log(
            'gamas',
            'Tambah update gamas '.$fresh->displayLabel(),
            $request->user(),
            $request,
            $fresh,
        );

        return response()->json([
            'message' => 'Update berhasil disimpan.',
            'data' => $this->serializeUpdate($update),
        ], 201);
    }

    public function export(Request $request)
    {
        $from = $request->string('from', now()->toDateString())->toString();
        $to = $request->string('to', $from)->toString();
        $odc = trim($request->string('odc_name')->toString()) ?: null;
        $search = trim($request->string('search')->toString());
        $unmapped = $this->isUnmappedOdcFilter($odc);

        $rows = DailyGamas::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('location_label', 'like', "%{$search}%")
                        ->orWhere('impact', 'like', "%{$search}%")
                        ->orWhere('problem', 'like', "%{$search}%");
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
            ->map(fn (DailyGamas $r) => [
                $r->report_date?->toDateString(),
                DailyGamas::kindLabel($r->gamas_kind),
                $r->location_label,
                $r->impact,
                $r->odc_name,
                $r->problem,
                $r->action,
                $r->shift,
                $r->status,
                $r->start_problem?->toDateString(),
                $r->end_problem?->toDateString(),
                $r->created_at?->format('H:i'),
                $r->cleared_at?->format('H:i'),
                $r->creator?->name,
                $r->clearer?->name,
            ]);

        return ExcelExport::download(
            'gamas-'.$from.'-'.$to.'.xlsx',
            ['Tanggal', 'Jenis', 'Lokasi', 'Impact', 'ODC/Site', 'Problem', 'Action', 'Shift', 'Status', 'Start Problem', 'End Problem', 'Jam Open', 'Jam Close', 'Input oleh', 'Clear oleh'],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    protected function serializeUpdate(DailyGamasUpdate $update): array
    {
        return [
            'id' => $update->id,
            'daily_gamas_id' => $update->daily_gamas_id,
            'update_text' => $update->update_text,
            'created_by' => $update->created_by,
            'author_name' => $update->author?->name,
            'created_at' => $update->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $request->merge([
            'start_problem' => $request->filled('start_problem') ? $request->input('start_problem') : null,
            'end_problem' => $request->filled('end_problem') ? $request->input('end_problem') : null,
        ]);

        return $request->validate([
            'report_date' => 'required|date',
            'gamas_kind' => 'required|in:'.implode(',', DailyGamas::KINDS),
            'location_label' => 'required|string|max:255',
            'impact' => 'nullable|string|max:100',
            'odc_name' => 'nullable|string|max:255',
            'problem' => 'nullable|string|max:255',
            'action' => 'nullable|string',
            'start_problem' => 'nullable|date',
            'end_problem' => 'nullable|date',
            'status' => 'required|in:'.implode(',', ReportStatus::options()),
            'shift' => 'nullable|integer|in:1,2,3',
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function assertActionForClear(array $data): void
    {
        if (($data['status'] ?? null) === ReportStatus::CLEAR && trim((string) ($data['action'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'action' => 'Action/perbaikan wajib diisi sebelum status Clear.',
            ]);
        }
    }

    /**
     * Item dalam rentang tanggal + item open dari sebelum rentang (belum Clear)
     * + item yang di-clear dalam rentang (meski report_date lebih lama).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyGamas>  $query
     */
    protected function forDateRangeOrStillOpen($query, string $from, string $to): void
    {
        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();

        $query->where(function ($q) use ($from, $to, $fromStart, $toEnd) {
            $q->whereBetween('report_date', [$from, $to])
                ->orWhere(function ($q2) use ($from) {
                    $q2->whereDate('report_date', '<', $from)
                        ->where(fn ($q3) => $this->scopeNotClear($q3));
                })
                ->orWhere(function ($q2) use ($fromStart, $toEnd) {
                    $q2->whereNotNull('cleared_at')
                        ->whereBetween('cleared_at', [$fromStart, $toEnd]);
                });
        });
    }

    /**
     * Samakan dashboard *Clears: status Clear + cleared_at (atau report_date jika cleared_at null).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyGamas>  $query
     */
    protected function forClearedInRange($query, string $from, string $to): void
    {
        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();

        $query->whereRaw('LOWER(status) = ?', [strtolower(ReportStatus::CLEAR)])
            ->where(function ($q) use ($from, $to, $fromStart, $toEnd) {
                $q->whereBetween('cleared_at', [$fromStart, $toEnd])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('report_date', [$from, $to]);
                    });
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyGamas>  $query
     */
    protected function scopeNotClear($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('status')
                ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyGamas>  $query
     */
    protected function applyListDateFilter($query, Request $request, string $from, string $to): void
    {
        if ($this->isClearMode($request)) {
            $this->forClearedInRange($query, $from, $to);
        } else {
            $this->forDateRangeOrStillOpen($query, $from, $to);
        }
    }

    protected function isClearMode(Request $request): bool
    {
        return mb_strtolower(trim($request->string('mode')->toString())) === 'clear';
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
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DailyGamas>  $query
     */
    protected function scopeEmptyOdcName($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('odc_name')
                ->orWhere('odc_name', '')
                ->orWhereRaw("TRIM(odc_name) = ''");
        });
    }
}
