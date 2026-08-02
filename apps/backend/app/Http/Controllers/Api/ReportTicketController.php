<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportTicketResource;
use App\Models\ReportTicket;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportTicketController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ReportTicket::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->latest('id');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('odc_name', 'like', "%{$search}%")
                    ->orWhere('problem', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        if ($from !== '') {
            $query->whereDate('opened_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('opened_at', '<=', $to);
        }

        if ($odc = trim($request->string('odc_name')->toString())) {
            $query->where('odc_name', $odc);
        }

        return ReportTicketResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $ticket = ReportTicket::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);
        $this->syncClearFields($ticket, $data['status'], $request->user()->id, null);
        $ticket->save();

        $this->activity->log(
            'ticket',
            'Tambah report ticket '.($ticket->customer_name ?: '#'.$ticket->id),
            $request->user(),
            $request,
            $ticket,
        );

        return response()->json([
            'message' => 'Ticket berhasil ditambahkan.',
            'data' => new ReportTicketResource($ticket->load(['creator:id,name', 'clearer:id,name'])),
        ], 201);
    }

    public function show(ReportTicket $reportTicket): ReportTicketResource
    {
        return new ReportTicketResource($reportTicket->load(['creator:id,name', 'clearer:id,name']));
    }

    public function update(Request $request, ReportTicket $reportTicket): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $previous = $reportTicket->status;
        $reportTicket->fill($data);
        $this->syncClearFields($reportTicket, $reportTicket->status, $request->user()->id, $previous);
        $reportTicket->save();

        $this->activity->log(
            'ticket',
            'Edit report ticket '.($reportTicket->customer_name ?: '#'.$reportTicket->id),
            $request->user(),
            $request,
            $reportTicket,
        );

        return response()->json([
            'message' => 'Ticket berhasil diperbarui.',
            'data' => new ReportTicketResource($reportTicket->fresh(['creator:id,name', 'clearer:id,name'])),
        ]);
    }

    public function destroy(ReportTicket $reportTicket): JsonResponse
    {
        $label = $reportTicket->customer_name ?: '#'.$reportTicket->id;
        $reportTicket->delete();
        $this->activity->log('ticket', "Hapus report ticket {$label}", request()->user(), request());

        return response()->json(['message' => 'Ticket berhasil dihapus.']);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => ReportTicket::count(),
            'on_progress' => ReportTicket::where('status', 'On-Progress')->count(),
            'clear' => ReportTicket::where('status', 'Clear')->count(),
            'closed' => ReportTicket::where('status', 'Closed')->count(),
        ]);
    }

    public function export(Request $request)
    {
        $query = ReportTicket::query()
            ->with(['creator:id,name', 'clearer:id,name'])
            ->orderBy('opened_at')
            ->orderBy('id');

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        if ($from !== '') {
            $query->whereDate('opened_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('opened_at', '<=', $to);
        }
        if ($odc = trim($request->string('odc_name')->toString())) {
            $query->where('odc_name', $odc);
        }
        if ($status = $request->string('status')->toString()) {
            if ($status !== '' && $status !== 'all') {
                $query->where('status', $status);
            }
        }

        $rows = $query->get()->map(fn (ReportTicket $r) => [
            $r->opened_at?->toDateString(),
            $r->closed_at?->toDateString(),
            $r->odc_name,
            $r->location,
            $r->customer_code,
            $r->customer_name,
            $r->problem,
            $r->action,
            $r->status,
            $r->creator?->name,
            $r->clearer?->name,
        ]);

        $labelFrom = $from !== '' ? $from : 'all';
        $labelTo = $to !== '' ? $to : 'all';

        return \App\Support\ExcelExport::download(
            'report-ticket-'.$labelFrom.'-'.$labelTo.'.xlsx',
            ['Opened', 'Closed', 'ODC/Site', 'Lokasi', 'Kode', 'Nama', 'Problem', 'Action', 'Status', 'Input oleh', 'Close oleh'],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'location' => 'nullable|string|max:255',
            'odc_name' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:100',
            'customer_name' => ($updating ? 'sometimes' : 'required').'|string|max:255',
            'problem' => 'nullable|string|max:255',
            'action' => 'nullable|string',
            'status' => ($updating ? 'sometimes' : 'required').'|in:On-Progress,Clear,Closed',
            'opened_at' => 'nullable|date',
            'closed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
    }

    private function syncClearFields(ReportTicket $ticket, string $status, int $userId, ?string $previous): void
    {
        if (in_array($status, ['Clear', 'Closed'], true)) {
            if ($previous !== $status || ! $ticket->cleared_by) {
                $ticket->cleared_by = $userId;
                $ticket->cleared_at = now();
            }
            if (! $ticket->closed_at && $status === 'Closed') {
                $ticket->closed_at = today();
            }

            return;
        }

        if (in_array($previous, ['Clear', 'Closed'], true)) {
            $ticket->cleared_by = null;
            $ticket->cleared_at = null;
        }
    }
}
