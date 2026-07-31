<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Activation;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DailyComplaint;
use App\Models\Dismantle;
use App\Services\Audit\ActivityLogger;
use App\Services\Customer\CustomerImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query()->with('odc:id,name,code')->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('odc', fn ($odc) => $odc->where('name', 'like', "%{$search}%"));
            });
        }

        if ($odcId = $request->integer('odc_id')) {
            $query->where('odc_id', $odcId);
        }

        if ($request->string('status')->toString() === 'aktif') {
            $query->where('status', 'active');
        } elseif ($request->string('status')->toString() === 'pending') {
            $query->where('status', '!=', 'active');
        } elseif ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return CustomerResource::collection($query->paginate($perPage));
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'inactive' => Customer::where('status', 'inactive')->count(),
            'suspended' => Customer::where('status', 'suspended')->count(),
            'pending' => Customer::where('status', '!=', 'active')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:100', 'unique:customers,customer_code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'odc_id' => ['nullable', 'exists:odcs,id'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ]);

        $customer = Customer::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
            'activated_at' => now(),
        ]);

        $this->activity->log('customer', "Tambah pelanggan {$customer->name}", $request->user(), $request, $customer);

        return response()->json([
            'message' => 'Pelanggan berhasil ditambahkan.',
            'data' => new CustomerResource($customer->load('odc')),
        ], 201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->load('odc'));
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'customer_code' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('customers', 'customer_code')->ignore($customer->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'odc_id' => ['nullable', 'exists:odcs,id'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
        ]);

        $customer->update($data);

        $this->activity->log('customer', "Edit pelanggan {$customer->name}", $request->user(), $request, $customer);

        return response()->json([
            'message' => 'Pelanggan berhasil diperbarui.',
            'data' => new CustomerResource($customer->fresh()->load('odc')),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $name = $customer->name;
        $customer->delete();

        $this->activity->log('customer', "Hapus pelanggan {$name}", request()->user(), request());

        return response()->json([
            'message' => 'Pelanggan berhasil dihapus.',
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'pelanggan-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Kode Pelanggan', 'Nama Pelanggan', 'Alamat', 'No HP', 'ODC']);

            Customer::query()->with('odc:id,name')->orderBy('name')->chunk(200, function ($rows) use ($handle) {
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

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:20480'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $importer = new CustomerImportService;

        try {
            if (in_array($extension, ['xls', 'xlsx'], true)) {
                $parsed = (new \App\Services\Customer\CustomerSpreadsheetReader)->read($file->getRealPath());
                $importer->import($parsed['header'], $parsed['rows']);
            } else {
                $handle = fopen($file->getRealPath(), 'r');

                if ($handle === false) {
                    return response()->json(['message' => 'File tidak dapat dibaca.'], 422);
                }

                $header = fgetcsv($handle);
                if (! $header) {
                    fclose($handle);

                    return response()->json(['message' => 'File CSV kosong atau tidak valid.'], 422);
                }

                $rows = [];
                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);

                $importer->import($header, $rows);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activity->log(
            'customer',
            "Import pelanggan: {$importer->success} berhasil, {$importer->failed} gagal",
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => "Import selesai: {$importer->success} berhasil, {$importer->failed} gagal.",
            'success' => $importer->success,
            'failed' => $importer->failed,
            'errors' => $importer->errors,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $query = Customer::query()->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(15);

        $customers->getCollection()->transform(function (Customer $customer) {
            $events = collect();

            ActivityLog::where('subject_type', Customer::class)
                ->where('subject_id', $customer->id)
                ->latest()
                ->limit(5)
                ->get()
                ->each(fn ($log) => $events->push([
                    'type' => 'activity',
                    'title' => $log->action,
                    'date' => $log->created_at?->toIso8601String(),
                ]));

            Activation::where('customer_id', $customer->id)->latest()->limit(3)->get()
                ->each(fn ($a) => $events->push([
                    'type' => 'activation',
                    'title' => "Aktivasi — {$a->status}",
                    'date' => $a->created_at?->toIso8601String(),
                ]));

            Dismantle::where('customer_id', $customer->id)->latest()->limit(3)->get()
                ->each(fn ($d) => $events->push([
                    'type' => 'dismantle',
                    'title' => "Dismantle — {$d->status}",
                    'date' => $d->created_at?->toIso8601String(),
                ]));

            DailyComplaint::where('customer_id', $customer->id)
                ->latest('report_date')
                ->limit(5)
                ->get()
                ->each(fn ($c) => $events->push([
                    'type' => 'complaint',
                    'title' => 'Komplain — '.($c->problem ?: $c->status),
                    'date' => $c->report_date?->toIso8601String() ?? $c->created_at?->toIso8601String(),
                ]));

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'pppoe' => $customer->pppoe,
                'status' => $customer->status,
                'package' => $customer->package,
                'complaint_count_90d' => DailyComplaint::where('customer_id', $customer->id)
                    ->where('complaint_type', DailyComplaint::TYPE_INDIVIDUAL)
                    ->whereDate('report_date', '>=', now()->subDays(90)->toDateString())
                    ->count(),
                'events' => $events->sortByDesc('date')->values()->take(10)->all(),
            ];
        });

        return response()->json($customers);
    }
}
