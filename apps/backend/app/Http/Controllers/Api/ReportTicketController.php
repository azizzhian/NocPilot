<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportTicketResource;
use App\Models\DailyComplaint;
use App\Models\ReportTicket;
use App\Models\ReportTicketUpdate;
use App\Services\Audit\ActivityLogger;
use App\Support\ReportStatus;
use App\Support\TicketStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportTicketController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->filteredQuery($request);

        return ReportTicketResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $ticket = DB::transaction(function () use ($request, $data) {
            $complaint = null;

            if (! empty($data['complaint_id'])) {
                $complaint = DailyComplaint::query()
                    ->lockForUpdate()
                    ->findOrFail($data['complaint_id']);

                $this->assertCanEscalate($complaint, $request->user()?->id);

                $defaults = [
                    'source' => 'complaint',
                    'customer_id' => $complaint->customer_id,
                    'customer_name' => $complaint->customer_name,
                    'customer_code' => $complaint->customer_code,
                    'customer_phone' => $complaint->phone_normalized,
                    'odc_name' => $complaint->odc_name,
                    'location' => $complaint->location_label ?: $complaint->odc_name,
                    'problem' => $complaint->problem,
                    'action' => $complaint->action,
                    'opened_at' => $complaint->report_date?->toDateString(),
                ];
                $data = array_merge($defaults, array_filter($data, fn ($value) => $value !== null && $value !== ''));
                $data['source'] = 'complaint';
                $data['complaint_id'] = $complaint->id;
                $data['escalated_at'] = now();
                $data['source_complaint_number'] = $complaint->complaintNumber();
                $data['source_snapshot'] = [
                    'complaint_id' => $complaint->id,
                    'complaint_number' => $complaint->complaintNumber(),
                    'report_date' => $complaint->report_date?->toDateString(),
                    'customer_name' => $complaint->customer_name,
                    'customer_code' => $complaint->customer_code,
                    'phone' => $complaint->phone_normalized,
                    'odc_name' => $complaint->odc_name,
                    'problem' => $complaint->problem,
                    'action' => $complaint->action,
                    'creator_name' => $complaint->creator?->name,
                    'created_at' => $complaint->created_at?->toIso8601String(),
                    'escalation_reason' => $data['escalation_reason'] ?? null,
                ];
            } else {
                $data['source'] = 'direct';
                $data['complaint_id'] = null;
            }

            $status = $data['status'] ?? TicketStatus::LEGACY_ON_PROGRESS;
            if (! empty($data['assigned_to'])) {
                $data['assigned_at'] = now();
            }
            $data['status'] = $status;
            if (TicketStatus::isClose($status)) {
                $this->assertActionForClose($data['action'] ?? null);
            }

            $ticket = ReportTicket::create([...$data, 'created_by' => $request->user()->id]);
            $this->syncClearFields($ticket, $status, $request->user()->id, null);
            $ticket->save();

            if ($complaint) {
                $complaint->ticket_id = $ticket->id;
                $complaint->escalated_at = $ticket->escalated_at;
                $complaint->save();

                $this->activity->log(
                    'complaint',
                    'Komplain '.$complaint->complaintNumber().' dieskalasikan ke ticket #'.$ticket->id,
                    $request->user(),
                    $request,
                    $complaint,
                    ['ticket_id' => $ticket->id, 'complaint_id' => $complaint->id],
                );

                if (TicketStatus::isClose($status)) {
                    $this->syncComplaintOnFinish($ticket, $request->user(), $request, $ticket->action);
                }
            }

            $this->logUpdate($ticket, 'created', $request->user(), [
                'note' => $complaint
                    ? 'Ticket dibuat dari komplain '.$complaint->complaintNumber()
                    : 'Ticket langsung dibuat.',
            ]);
            $this->activity->log(
                'ticket',
                'Tambah ticket '.($ticket->customer_name ?: '#'.$ticket->id),
                $request->user(),
                $request,
                $ticket,
                ['source' => $ticket->source, 'complaint_id' => $ticket->complaint_id],
            );

            return $ticket;
        });

        return response()->json([
            'message' => 'Ticket berhasil ditambahkan.',
            'data' => new ReportTicketResource($ticket->load([
                'creator:id,name', 'clearer:id,name', 'assignee:id,name', 'updates.author:id,name',
            ])),
        ], 201);
    }

    public function show(ReportTicket $reportTicket): ReportTicketResource
    {
        return new ReportTicketResource($reportTicket->load([
            'creator:id,name', 'clearer:id,name', 'assignee:id,name',
            'complaint:id,customer_name,report_date,created_by,status',
            'updates.author:id,name',
        ]));
    }

    public function update(Request $request, ReportTicket $reportTicket): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $previous = $reportTicket->status;
        $assigneeChanged = array_key_exists('assigned_to', $data)
            && (int) ($data['assigned_to'] ?? 0) !== (int) ($reportTicket->assigned_to ?? 0);

        $ticket = DB::transaction(function () use ($request, $reportTicket, $data, $previous, $assigneeChanged) {
            $reportTicket->fill($data);

            if ($assigneeChanged) {
                $reportTicket->assigned_at = ! empty($data['assigned_to']) ? now() : null;
            }

            $status = $reportTicket->status;
            if (TicketStatus::isClose($status)) {
                $this->assertActionForClose($reportTicket->action);
            }
            $this->syncClearFields($reportTicket, $status, $request->user()->id, $previous);
            $reportTicket->save();

            if ($status !== $previous) {
                $this->applyTransition($reportTicket, $status, $request->user(), $request, $data['notes'] ?? null);
            } elseif ($assigneeChanged) {
                $this->logUpdate($reportTicket, 'assigned', $request->user(), [
                    'note' => 'Teknisi ditugaskan ulang.',
                ]);
            }

            $this->activity->log(
                'ticket',
                'Edit ticket '.($reportTicket->customer_name ?: '#'.$reportTicket->id),
                $request->user(),
                $request,
                $reportTicket,
            );

            return $reportTicket;
        });

        return response()->json([
            'message' => 'Ticket berhasil diperbarui.',
            'data' => new ReportTicketResource($ticket->fresh([
                'creator:id,name', 'clearer:id,name', 'assignee:id,name', 'updates.author:id,name',
            ])),
        ]);
    }

    public function destroy(ReportTicket $reportTicket): JsonResponse
    {
        $label = $reportTicket->customer_name ?: '#'.$reportTicket->id;
        $reportTicket->delete();
        $this->activity->log('ticket', "Hapus ticket {$label}", request()->user(), request());

        return response()->json(['message' => 'Ticket berhasil dihapus.']);
    }

    public function stats(Request $request): JsonResponse
    {
        $visible = $this->filteredQuery($request);

        $statusCounts = $this->statusDistributionQuery($request)
            ->pluck('total', 'status');

        $statusOptions = TicketStatus::options();
        $distribution = [];
        foreach ($statusOptions as $status) {
            $distribution[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        $countFor = fn (): int => (int) (clone $visible)->count();
        $countIn = fn (array $statuses): int => (int) (clone $visible)
            ->whereIn('status', $statuses)
            ->count();

        return response()->json([
            'total' => $countFor(),
            'active' => $countIn(TicketStatus::kpiActiveStatuses()),
            'from_complaint' => (int) (clone $visible)->whereNotNull('complaint_id')->count(),
            'finished' => $countIn(TicketStatus::closeStatuses()),
            'close_count' => $countIn(TicketStatus::closeStatuses()),
            'direct' => (int) (clone $visible)->whereNull('complaint_id')->count(),
            'on_progress' => $distribution[TicketStatus::LEGACY_ON_PROGRESS],
            'clear' => $distribution[TicketStatus::LEGACY_CLEAR],
            'closed' => $distribution[TicketStatus::LEGACY_CLOSED],
            'open' => $distribution[TicketStatus::OPEN],
            'assigned' => $distribution[TicketStatus::ASSIGNED],
            'on_site' => $distribution[TicketStatus::ON_SITE],
            'in_progress' => $distribution[TicketStatus::IN_PROGRESS],
            'completed' => $distribution[TicketStatus::COMPLETED],
            'cancelled' => $distribution[TicketStatus::CANCELLED],
            'status_counts' => $distribution,
            'status_options' => $statusOptions,
            'form_options' => TicketStatus::formOptions(),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->with('assignee:id,name')
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ReportTicket $r) => [
                $r->opened_at?->toDateString(),
                $r->closed_at?->toDateString(),
                $r->odc_name,
                $r->location,
                $r->customer_code,
                $r->customer_name,
                $r->customer_phone,
                $r->problem,
                $r->action,
                TicketStatus::displayLabel($r->status),
                $r->source === 'complaint' ? 'Dari Komplain' : 'Tiket Langsung',
                $r->source_complaint_number,
                $r->escalation_reason,
                $r->job_type,
                $r->priority,
                $r->assignee?->name,
                $r->created_at?->format('H:i'),
                $r->cleared_at?->format('H:i'),
                $r->creator?->name,
                $r->clearer?->name,
            ]);

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        return \App\Support\ExcelExport::download(
            'report-ticket-'.($from !== '' ? $from : 'all').'-'.($to !== '' ? $to : 'all').'.xlsx',
            [
                'Opened', 'Closed', 'ODC/Site', 'Lokasi', 'Kode', 'Nama', 'No. Telepon',
                'Problem', 'Action', 'Status', 'Sumber', 'No. Komplain Asal', 'Alasan Eskalasi',
                'Jenis Pekerjaan', 'Prioritas', 'Teknisi', 'Jam Open', 'Jam Close', 'Input oleh', 'Close oleh',
            ],
            $rows,
        );
    }

    public function updates(ReportTicket $reportTicket): JsonResponse
    {
        $items = $reportTicket->updates()->with('author:id,name')->get();

        return response()->json(['data' => $items->map(fn ($u) => [
            'id' => $u->id,
            'report_ticket_id' => $u->report_ticket_id,
            'type' => $u->type,
            'check_result' => $u->check_result,
            'repair_action' => $u->repair_action,
            'material' => $u->material,
            'photo' => $u->photo,
            'work_started_at' => $u->work_started_at?->toIso8601String(),
            'work_ended_at' => $u->work_ended_at?->toIso8601String(),
            'note' => $u->note,
            'author_name' => $u->author?->name,
            'created_at' => $u->created_at?->toIso8601String(),
        ])->all()]);
    }

    public function storeUpdate(Request $request, ReportTicket $reportTicket): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['started', 'progress', 'completed'])],
            'action' => 'nullable|string|max:2000',
            'check_result' => 'nullable|string|max:2000',
            'repair_action' => 'nullable|string|max:2000',
            'material' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
            'work_started_at' => 'nullable|date',
            'work_ended_at' => 'nullable|date',
            'photo' => 'nullable|file|image|max:2048',
        ]);

        if (blank($data['check_result'] ?? null) && blank($data['repair_action'] ?? null)
            && blank($data['note'] ?? null) && blank($data['action'] ?? null)) {
            throw ValidationException::withMessages([
                'check_result' => 'Isi hasil pengecekan, tindakan perbaikan, atau catatan.',
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('ticket-photos', 'public');
        }
        unset($data['photo']);
        $data['photo'] = $photoPath;
        $data['type'] = $data['type'] ?? 'progress';

        $update = DB::transaction(function () use ($request, $reportTicket, $data) {
            $update = ReportTicketUpdate::create([
                ...$data,
                'report_ticket_id' => $reportTicket->id,
                'created_by' => $request->user()->id,
            ]);

            $type = $data['type'];
            $previous = $reportTicket->status;
            if ($type === 'completed') {
                if (! blank($data['action'] ?? null)) {
                    $reportTicket->action = $data['action'];
                }
                $this->assertActionForClose($reportTicket->action);
                $reportTicket->status = TicketStatus::CLOSE;
                $reportTicket->work_completed_at = now();
                $this->syncClearFields($reportTicket, TicketStatus::CLOSE, $request->user()->id, $previous);
                $reportTicket->save();
                $this->applyTransition($reportTicket, TicketStatus::CLOSE, $request->user(), $request, $data['note'] ?? null);
            } elseif ($type === 'started') {
                $reportTicket->work_started_at ??= now();
                $reportTicket->save();
            }

            $this->activity->log(
                'ticket',
                'Update pekerjaan ticket #'.$reportTicket->id,
                $request->user(),
                $request,
                $reportTicket,
                ['update_id' => $update->id, 'type' => $type],
            );

            return $update;
        });

        return response()->json([
            'message' => 'Update pekerjaan berhasil disimpan.',
            'data' => [
                'id' => $update->id,
                'report_ticket_id' => $update->report_ticket_id,
                'type' => $update->type,
                'check_result' => $update->check_result,
                'repair_action' => $update->repair_action,
                'material' => $update->material,
                'photo' => $update->photo,
                'work_started_at' => $update->work_started_at?->toIso8601String(),
                'work_ended_at' => $update->work_ended_at?->toIso8601String(),
                'note' => $update->note,
                'author_name' => $request->user()->name,
                'created_at' => $update->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function setStatus(Request $request, ReportTicket $reportTicket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([...TicketStatus::writableStatuses(), 'Close'])],
            'note' => 'nullable|string|max:2000',
            'action' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $reportTicket, $data) {
            $previous = $reportTicket->status;
            $status = $this->normalizeStatus($data['status']);
            $reportTicket->status = $status;
            if (! blank($data['action'] ?? null)) {
                $reportTicket->action = $data['action'];
            }
            if (TicketStatus::isClose($status)) {
                $this->assertActionForClose($reportTicket->action);
            }
            if (TicketStatus::isFinished($status)) {
                $reportTicket->work_completed_at ??= now();
            }
            $this->syncClearFields($reportTicket, $status, $request->user()->id, $previous);
            $reportTicket->save();
            $this->applyTransition($reportTicket, $status, $request->user(), $request, $data['note'] ?? null);
        });

        return response()->json([
            'message' => 'Status ticket berhasil diperbarui.',
            'data' => new ReportTicketResource($reportTicket->fresh([
                'creator:id,name', 'clearer:id,name', 'assignee:id,name', 'updates.author:id,name',
            ])),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\ReportTicket> */
    protected function statusDistributionQuery(Request $request)
    {
        return $this->filteredQuery($request, true)
            ->reorder()
            ->setEagerLoads([])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status');
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\ReportTicket> */
    private function filteredQuery(Request $request, bool $ignoreStatus = false)
    {
        $query = ReportTicket::query()
            ->with(['creator:id,name', 'clearer:id,name', 'assignee:id,name'])
            ->latest('id');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('odc_name', 'like', "%{$search}%")
                    ->orWhere('problem', 'like', "%{$search}%")
                    ->orWhere('source_complaint_number', 'like', "%{$search}%");
                $ticketNumber = ltrim($search, '#');
                if ($ticketNumber !== '' && ctype_digit($ticketNumber)) {
                    $q->orWhere('id', (int) $ticketNumber);
                }
            });
        }

        if ($source = $request->string('source')->toString()) {
            if (in_array($source, ['direct', 'complaint'], true)) {
                $query->where('source', $source);
            }
        }

        if ($this->isClearMode($request)) {
            $query->whereIn('status', TicketStatus::closeStatuses());
            $this->applyClearDateFilter($query, $request);
        } else {
            if (! $ignoreStatus) {
                $statuses = TicketStatus::filterStatuses($request->string('status')->toString() ?: null);
                if ($statuses !== null) {
                    $query->whereIn('status', $statuses);
                }
            }
            $this->applyOpenedDateFilter($query, $request);
        }

        if ($odc = trim($request->string('odc_name')->toString())) {
            $this->applyOdcNameFilter($query, $odc);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        $data = $request->validate([
            'complaint_id' => 'nullable|integer|exists:daily_complaints,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'location' => 'nullable|string|max:255',
            'odc_name' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:100',
            'customer_name' => ($updating ? 'sometimes' : ($request->filled('complaint_id') ? 'sometimes' : 'required')).'|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'job_type' => 'nullable|string|max:100',
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'visit_schedule' => 'nullable|date',
            'escalation_reason' => ($request->filled('complaint_id') && ! $updating ? 'required' : 'nullable').'|string|max:2000',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'problem' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:2000',
            'status' => 'sometimes|'.Rule::in([...TicketStatus::writableStatuses(), 'Close']),
            'opened_at' => 'nullable|date',
            'closed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (isset($data['status'])) {
            $data['status'] = $this->normalizeStatus($data['status']);
        }

        return $data;
    }

    private function normalizeStatus(string $status): string
    {
        return strcasecmp($status, 'Close') === 0 ? TicketStatus::CLOSE : $status;
    }

    private function assertActionForClose(?string $action): void
    {
        if (blank($action)) {
            throw ValidationException::withMessages([
                'action' => 'Action/perbaikan wajib diisi sebelum ticket ditutup.',
            ]);
        }
    }

    private function assertCanEscalate(DailyComplaint $complaint, ?int $userId): void
    {
        if (! $complaint->isOwnedBy($userId)) {
            abort(403, 'Hanya pembuat komplain yang dapat membuat tiket dari komplain.');
        }

        if (ReportStatus::isClear($complaint->status)) {
            throw ValidationException::withMessages([
                'complaint_id' => 'Komplain yang sudah Clear tidak dapat dieskalasikan.',
            ]);
        }

        $active = ReportTicket::query()
            ->where('complaint_id', $complaint->id)
            ->whereIn('status', TicketStatus::activeStatuses())
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'complaint_id' => 'Komplain ini sudah memiliki tiket aktif yang dibuat petugas lain.',
            ]);
        }
    }

    private function applyTransition(
        ReportTicket $ticket,
        string $status,
        \App\Models\User $user,
        Request $request,
        ?string $note = null,
    ): void {
        if ($status === TicketStatus::CANCELLED) {
            $this->syncComplaintOnCancel($ticket, $user, $request);
        } elseif (TicketStatus::isFinished($status)) {
            $this->syncComplaintOnFinish($ticket, $user, $request, $note);
        }

        $this->logUpdate($ticket, $status === TicketStatus::CANCELLED ? 'cancelled' : (TicketStatus::isFinished($status) ? 'completed' : 'progress'), $user, [
            'note' => $note ?: 'Status ticket menjadi '.$status.'.',
        ]);
        $this->activity->log(
            'ticket',
            'Status ticket #'.$ticket->id.' menjadi '.$status,
            $user,
            $request,
            $ticket,
            ['status' => $status, 'complaint_id' => $ticket->complaint_id],
        );
    }

    private function syncComplaintOnFinish(ReportTicket $ticket, \App\Models\User $user, Request $request, ?string $note): void
    {
        if (! $ticket->complaint_id) {
            return;
        }

        $complaint = DailyComplaint::query()->lockForUpdate()->find($ticket->complaint_id);
        if (! $complaint || ReportStatus::isClear($complaint->status)) {
            return;
        }

        $previous = $complaint->status;
        $complaint->status = ReportStatus::WAITING_CONFIRMATION;
        $complaint->handled_at = now();
        $complaint->handled_by = $user->id;
        $complaint->save();

        $this->activity->log(
            'complaint',
            'Komplain '.$complaint->complaintNumber().' menunggu konfirmasi setelah ticket #'.$ticket->id.' selesai',
            $user,
            $request,
            $complaint,
            ['ticket_id' => $ticket->id, 'complaint_id' => $complaint->id, 'previous_status' => $previous, 'note' => $note],
        );
    }

    private function syncComplaintOnCancel(ReportTicket $ticket, \App\Models\User $user, Request $request): void
    {
        if (! $ticket->complaint_id) {
            return;
        }

        $complaint = DailyComplaint::query()->lockForUpdate()->find($ticket->complaint_id);
        if (! $complaint || ReportStatus::isClear($complaint->status)) {
            return;
        }

        $complaint->status = ReportStatus::ON_PROGRESS;
        $complaint->handled_at = null;
        $complaint->handled_by = null;
        $complaint->escalated_at = null;
        $complaint->save();

        $this->activity->log(
            'complaint',
            'Ticket #'.$ticket->id.' dibatalkan; komplain '.$complaint->complaintNumber().' kembali On-Progress',
            $user,
            $request,
            $complaint,
            ['ticket_id' => $ticket->id, 'complaint_id' => $complaint->id],
        );
    }

    private function logUpdate(ReportTicket $ticket, string $type, \App\Models\User $user, array $fields = []): ReportTicketUpdate
    {
        return ReportTicketUpdate::create([
            ...$fields,
            'report_ticket_id' => $ticket->id,
            'created_by' => $user->id,
            'type' => $type,
        ]);
    }

    private function applyOdcNameFilter($query, string $odc): void
    {
        $key = mb_strtolower(trim($odc));
        if ($key === '__none__' || $key === 'tanpa odc') {
            $query->where(function ($q) {
                $q->whereNull('odc_name')
                    ->orWhere('odc_name', '')
                    ->orWhereRaw("TRIM(odc_name) = ''");
            });

            return;
        }

        $query->where('odc_name', $odc);
    }

    private function isClearMode(Request $request): bool
    {
        return mb_strtolower(trim($request->string('mode')->toString())) === 'clear';
    }

    private function applyOpenedDateFilter($query, Request $request): void
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        if ($from !== '') {
            $query->whereDate('opened_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('opened_at', '<=', $to);
        }
    }

    private function applyClearDateFilter($query, Request $request): void
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        if ($from === '' && $to === '') {
            return;
        }

        $fromDate = $from !== '' ? $from : $to;
        $toDate = $to !== '' ? $to : $from;
        $fromStart = Carbon::parse($fromDate)->startOfDay();
        $toEnd = Carbon::parse($toDate)->endOfDay();

        $query->where(function ($q) use ($fromStart, $toEnd, $fromDate, $toDate) {
            $q->whereBetween('cleared_at', [$fromStart, $toEnd])
                ->orWhere(function ($q2) use ($fromDate, $toDate) {
                    $q2->whereNull('cleared_at')
                        ->whereBetween('closed_at', [$fromDate, $toDate]);
                });
        });
    }

    private function syncClearFields(ReportTicket $ticket, string $status, int $userId, ?string $previous): void
    {
        if (TicketStatus::isFinished($status)) {
            if ($previous !== $status || ! $ticket->cleared_by) {
                $ticket->cleared_by = $userId;
                $ticket->cleared_at = now();
            }
            if (! $ticket->closed_at && $status === TicketStatus::LEGACY_CLOSED) {
                $ticket->closed_at = today();
            }

            return;
        }

        if (TicketStatus::isFinished($previous)) {
            $ticket->cleared_by = null;
            $ticket->cleared_at = null;
        }
    }
}
