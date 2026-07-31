<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Odc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdcController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Odc::query()->with('pop:id,name,code')->withCount('odps')->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($popId = $request->integer('pop_id')) {
            $query->where('pop_id', $popId);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pop_id' => 'required|exists:pops,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:odcs,code',
            'status' => 'nullable|in:active,inactive,maintenance',
            'capacity' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $odc = Odc::create($data);

        return response()->json(['message' => 'ODC berhasil ditambahkan.', 'data' => $odc->load('pop:id,name,code')], 201);
    }

    public function show(Odc $odc): JsonResponse
    {
        return response()->json(['data' => $odc->load('pop:id,name,code')->loadCount('odps')]);
    }

    public function update(Request $request, Odc $odc): JsonResponse
    {
        $data = $request->validate([
            'pop_id' => 'sometimes|exists:pops,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:odcs,code,'.$odc->id,
            'status' => 'nullable|in:active,inactive,maintenance',
            'capacity' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $odc->update($data);

        return response()->json(['message' => 'ODC berhasil diperbarui.', 'data' => $odc->load('pop:id,name,code')]);
    }

    public function destroy(Odc $odc): JsonResponse
    {
        $odc->delete();

        return response()->json(['message' => 'ODC berhasil dihapus.']);
    }
}
