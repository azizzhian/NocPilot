<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Notification\NotificationService;
use App\Services\Ticket\TicketSlaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        private TicketSlaService $sla,
        private NotificationService $notifications,
        private ActivityLogger $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ticket::query()
            ->with(['assignee:id,name', 'customer:id,name,pppoe'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($priority = $request->string('priority')->toString()) {
            $query->where('priority', $priority);
        }

        return TicketResource::collection(
            $query->paginate(min($request->integer('per_page', 15), 50)),
        );
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'assigned' => Ticket::where('status', 'assigned')->count(),
            'progress' => Ticket::where('status', 'progress')->count(),
            'solved' => Ticket::where('status', 'solved')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'sla_breached' => Ticket::whereNotIn('status', ['solved', 'closed'])
                ->where('sla_deadline', '<', now())
                ->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'area' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $priority = $data['priority'];

        $ticket = Ticket::create([
            ...$data,
            'ticket_number' => Ticket::generateNumber(),
            'status' => isset($data['assigned_to']) ? 'assigned' : 'open',
            'sla_deadline' => $this->sla->calculateDeadline($priority),
            'assigned_at' => isset($data['assigned_to']) ? now() : null,
            'created_by' => $request->user()->id,
        ]);

        $this->sla->logActivity($ticket, 'created', 'Ticket dibuat', $request->user());

        if ($ticket->assigned_to) {
            $this->sla->logActivity($ticket, 'assigned', 'Ticket ditugaskan', $request->user());
        }

        $this->notifications->ticketCreated($ticket->load('assignee'));
        $this->activity->log('ticket', "Buat ticket {$ticket->ticket_number}", $request->user(), $request, $ticket);

        return response()->json([
            'message' => 'Ticket berhasil dibuat.',
            'data' => new TicketResource($ticket->load('assignee')),
        ], 201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource(
            $ticket->load(['assignee:id,name', 'customer', 'activities.user:id,name']),
        );
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['critical', 'high', 'medium', 'low'])],
            'status' => ['sometimes', Rule::in(['open', 'assigned', 'progress', 'solved', 'closed'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'internal_note' => ['nullable', 'string'],
            'area' => ['nullable', 'string', 'max:100'],
        ]);

        $oldStatus = $ticket->status;

        if (isset($data['priority']) && $data['priority'] !== $ticket->priority) {
            $data['sla_deadline'] = $this->sla->calculateDeadline($data['priority']);
        }

        if (isset($data['assigned_to']) && $data['assigned_to'] !== $ticket->assigned_to) {
            $data['assigned_at'] = now();
            if ($ticket->status === 'open') {
                $data['status'] = 'assigned';
            }
        }

        if (isset($data['status'])) {
            match ($data['status']) {
                'progress' => null,
                'solved' => $data['solved_at'] = now(),
                'closed' => $data['closed_at'] = now(),
                default => null,
            };
        }

        $ticket->update($data);

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->sla->logActivity(
                $ticket,
                'status_changed',
                "Status diubah: {$oldStatus} → {$data['status']}",
                $request->user(),
            );
            $this->notifications->ticketStatusChanged($ticket, $oldStatus);
        }

        $this->activity->log('ticket', "Edit ticket {$ticket->ticket_number}", $request->user(), $request, $ticket);

        return response()->json([
            'message' => 'Ticket berhasil diperbarui.',
            'data' => new TicketResource($ticket->fresh()->load('assignee')),
        ]);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $number = $ticket->ticket_number;
        $ticket->delete();
        $this->activity->log('ticket', "Hapus ticket {$number}", request()->user(), request());

        return response()->json(['message' => 'Ticket berhasil dihapus.']);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($data['assigned_to']);

        $ticket->update([
            'assigned_to' => $assignee->id,
            'assigned_at' => now(),
            'status' => in_array($ticket->status, ['open']) ? 'assigned' : $ticket->status,
        ]);

        $this->sla->logActivity(
            $ticket,
            'assigned',
            "Ditugaskan ke {$assignee->name}",
            $request->user(),
        );

        $this->notifications->ticketAssigned($ticket, $assignee->name);

        return response()->json([
            'message' => 'Teknisi berhasil ditugaskan.',
            'data' => new TicketResource($ticket->fresh()->load('assignee')),
        ]);
    }
}
