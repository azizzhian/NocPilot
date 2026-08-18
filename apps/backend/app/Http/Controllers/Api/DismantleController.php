<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DismantleResource;
use App\Models\Customer;
use App\Models\Dismantle;
use App\Services\Audit\ActivityLogger;
use App\Services\Dismantle\DismantleImportService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DismantleController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private ActivityLogger $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Dismantle::query()->with(['assignee:id,name', 'creator:id,name'])->latest();
        $this->applyFilters($query, $request, includeStatus: true);

        return DismantleResource::collection($query->paginate(15));
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Dismantle::query();
        $this->applyFilters($query, $request, includeStatus: false);

        return response()->json([
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'Pending')->count(),
            'on_progress' => (clone $query)->where('status', 'On-Progress')->count(),
            'clear' => (clone $query)->where('status', 'Clear')->count(),
        ]);
    }

    private function applyFilters($query, Request $request, bool $includeStatus = true): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($includeStatus) {
            $status = $request->string('status')->toString();
            if ($status !== '' && $status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($location = trim($request->string('location')->toString())) {
            $query->where('location', $location);
        }

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        if ($from !== '') {
            $query->whereDate('opened_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('opened_at', '<=', $to);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['Pending', 'On-Progress', 'Clear'])],
            'opened_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['customer_id'] ?? null) {
            $customer = Customer::find($data['customer_id']);
            $data['customer_name'] = $customer?->name ?? $data['customer_name'];
            $data['customer_code'] = $data['customer_code'] ?? $customer?->customer_code;
            $data['location'] = $data['location'] ?? $customer?->area;
        }

        $this->assertNoOpenDuplicate($data['customer_code'] ?? null, $data['status'] ?? 'On-Progress');

        $dismantle = Dismantle::create([
            ...$data,
            'reference' => Dismantle::generateReference(),
            'created_by' => $request->user()->id,
        ]);

        $this->notifications->dismantleCreated($dismantle);

        $label = $dismantle->customer_name ?: ($dismantle->reference ?: '#'.$dismantle->id);
        $this->activity->log(
            'dismantle',
            'Tambah dismantle '.$label,
            $request->user(),
            $request,
            $dismantle,
        );

        return response()->json([
            'message' => 'Dismantle berhasil dibuat.',
            'data' => new DismantleResource($dismantle->load(['assignee', 'creator'])),
        ], 201);
    }

    public function show(Dismantle $dismantle): DismantleResource
    {
        return new DismantleResource($dismantle->load('assignee', 'creator', 'customer'));
    }

    public function update(Request $request, Dismantle $dismantle): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['Pending', 'On-Progress', 'Clear'])],
            'opened_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $nextCode = array_key_exists('customer_code', $data) ? $data['customer_code'] : $dismantle->customer_code;
        $nextStatus = $data['status'] ?? $dismantle->status;
        $this->assertNoOpenDuplicate($nextCode, $nextStatus, $dismantle->id);

        $dismantle->update($data);

        $label = $dismantle->customer_name ?: ($dismantle->reference ?: '#'.$dismantle->id);
        $this->activity->log(
            'dismantle',
            'Edit dismantle '.$label,
            $request->user(),
            $request,
            $dismantle,
        );

        return response()->json([
            'message' => 'Dismantle berhasil diperbarui.',
            'data' => new DismantleResource($dismantle->fresh()->load(['assignee', 'creator'])),
        ]);
    }

    public function destroy(Dismantle $dismantle): JsonResponse
    {
        $label = $dismantle->customer_name ?: ($dismantle->reference ?: '#'.$dismantle->id);
        $dismantle->delete();

        $this->activity->log(
            'dismantle',
            'Hapus dismantle '.$label,
            request()->user(),
            request(),
        );

        return response()->json(['message' => 'Dismantle berhasil dihapus.']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($extension, ['csv', 'txt'], true)) {
            return response()->json(['message' => 'File harus berformat CSV.'], 422);
        }
        $path = $file->getRealPath();
        $probe = fopen($path, 'r');
        $firstLine = $probe ? (string) fgets($probe) : '';
        if ($probe) {
            fclose($probe);
        }
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return response()->json(['message' => 'File tidak dapat dibaca.'], 422);
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);

            return response()->json(['message' => 'File CSV kosong atau tidak valid.'], 422);
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        $importer = new DismantleImportService($request->user()->id);

        try {
            $importer->import($header, $rows);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activity->log(
            'dismantle',
            "Import dismantle: {$importer->success} dibuat, {$importer->skipped} dilewati, {$importer->failed} gagal",
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Import dismantle selesai.',
            'success' => $importer->success,
            'skipped' => $importer->skipped,
            'failed' => $importer->failed,
            'errors' => array_merge($importer->skippedMessages, $importer->errors),
        ]);
    }

    private function assertNoOpenDuplicate(?string $customerCode, string $status, ?int $exceptId = null): void
    {
        if (! in_array($status, Dismantle::openStatuses(), true)) {
            return;
        }

        $existing = Dismantle::findOpenByCustomerCode((string) $customerCode, $exceptId);
        if (! $existing) {
            return;
        }

        throw ValidationException::withMessages([
            'customer_code' => [
                "ID Pel {$existing->customer_code} sudah punya tiket dismantle terbuka ({$existing->reference}).",
            ],
        ]);
    }
}
