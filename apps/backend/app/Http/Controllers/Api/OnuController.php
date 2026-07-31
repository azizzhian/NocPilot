<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Onu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Onu::query()
            ->with(['odp:id,name,code', 'olt:id,name', 'customer:id,name,pppoe'])
            ->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'odp_id' => 'nullable|exists:odps,id',
            'olt_id' => 'nullable|exists:olts,id',
            'customer_id' => 'nullable|exists:customers,id',
            'serial' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:online,offline,los,maintenance',
            'rx_power' => 'nullable|numeric',
            'tx_power' => 'nullable|numeric',
            'pon_port' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $onu = Onu::create($data);

        return response()->json(['message' => 'ONU berhasil ditambahkan.', 'data' => $onu->load(['odp', 'olt', 'customer'])], 201);
    }

    public function show(Onu $onu): JsonResponse
    {
        return response()->json(['data' => $onu->load(['odp.odc', 'olt', 'customer'])]);
    }

    public function update(Request $request, Onu $onu): JsonResponse
    {
        $data = $request->validate([
            'odp_id' => 'nullable|exists:odps,id',
            'olt_id' => 'nullable|exists:olts,id',
            'customer_id' => 'nullable|exists:customers,id',
            'serial' => 'nullable|string|max:100',
            'name' => 'sometimes|string|max:255',
            'status' => 'nullable|in:online,offline,los,maintenance',
            'rx_power' => 'nullable|numeric',
            'tx_power' => 'nullable|numeric',
            'pon_port' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $onu->update($data);

        return response()->json(['message' => 'ONU berhasil diperbarui.', 'data' => $onu->load(['odp', 'olt', 'customer'])]);
    }

    public function destroy(Onu $onu): JsonResponse
    {
        $onu->delete();

        return response()->json(['message' => 'ONU berhasil dihapus.']);
    }
}
