<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivationResource;
use App\Models\Activation;
use App\Models\Customer;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ActivationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Activation::query()->with('assignee:id,name')->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        return ActivationResource::collection($query->paginate(15));
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Activation::count(),
            'pending' => Activation::where('status', 'pending')->count(),
            'scheduled' => Activation::where('status', 'scheduled')->count(),
            'in_progress' => Activation::where('status', 'in_progress')->count(),
            'completed' => Activation::where('status', 'completed')->count(),
            'cancelled' => Activation::where('status', 'cancelled')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'package' => ['required', 'string', 'max:50'],
            'area' => ['nullable', 'string', 'max:100'],
            'odp' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['customer_id'] ?? null) {
            $customer = Customer::find($data['customer_id']);
            $data['customer_name'] = $customer?->name ?? $data['customer_name'];
            $data['phone'] = $data['phone'] ?? $customer?->phone;
            $data['area'] = $data['area'] ?? $customer?->area;
        }

        $activation = Activation::create([
            ...$data,
            'reference' => Activation::generateReference(),
            'status' => isset($data['scheduled_at']) ? 'scheduled' : 'pending',
            'created_by' => $request->user()->id,
        ]);

        $this->notifications->activationCreated($activation);

        return response()->json([
            'message' => 'Aktivasi berhasil dibuat.',
            'data' => new ActivationResource($activation->load('assignee')),
        ], 201);
    }

    public function show(Activation $activation): ActivationResource
    {
        return new ActivationResource($activation->load('assignee', 'customer'));
    }

    public function update(Request $request, Activation $activation): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'scheduled', 'in_progress', 'completed', 'cancelled'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        $activation->update($data);

        return response()->json([
            'message' => 'Aktivasi berhasil diperbarui.',
            'data' => new ActivationResource($activation->fresh()->load('assignee')),
        ]);
    }

    public function destroy(Activation $activation): JsonResponse
    {
        $activation->delete();

        return response()->json(['message' => 'Aktivasi berhasil dihapus.']);
    }
}
