<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Odp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Odp::query()->with('odc:id,name,code,pop_id', 'odc.pop:id,name')->withCount('onus')->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($odcId = $request->integer('odc_id')) {
            $query->where('odc_id', $odcId);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'odc_id' => 'required|exists:odcs,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:odps,code',
            'status' => 'nullable|in:active,inactive,full,maintenance',
            'capacity' => 'nullable|integer|min:1|max:128',
            'used_ports' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $odp = Odp::create($data);

        return response()->json(['message' => 'ODP berhasil ditambahkan.', 'data' => $odp->load('odc.pop')], 201);
    }

    public function show(Odp $odp): JsonResponse
    {
        return response()->json(['data' => $odp->load('odc.pop')->loadCount('onus')]);
    }

    public function update(Request $request, Odp $odp): JsonResponse
    {
        $data = $request->validate([
            'odc_id' => 'sometimes|exists:odcs,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:odps,code,'.$odp->id,
            'status' => 'nullable|in:active,inactive,full,maintenance',
            'capacity' => 'nullable|integer|min:1|max:128',
            'used_ports' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $odp->update($data);

        return response()->json(['message' => 'ODP berhasil diperbarui.', 'data' => $odp->load('odc.pop')]);
    }

    public function destroy(Odp $odp): JsonResponse
    {
        $odp->delete();

        return response()->json(['message' => 'ODP berhasil dihapus.']);
    }
}
