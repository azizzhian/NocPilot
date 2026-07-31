<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Olt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OltController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Olt::query()->with('pop:id,name,code')->withCount('onus')->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
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
            'ip' => 'nullable|ip',
            'status' => 'nullable|in:online,offline,maintenance',
            'capacity' => 'nullable|integer|min:0',
            'pon_ports' => 'nullable|integer|min:1|max:64',
            'notes' => 'nullable|string',
        ]);

        $olt = Olt::create($data);

        return response()->json(['message' => 'OLT berhasil ditambahkan.', 'data' => $olt->load('pop:id,name,code')], 201);
    }

    public function show(Olt $olt): JsonResponse
    {
        return response()->json(['data' => $olt->load('pop:id,name,code')->loadCount('onus')]);
    }

    public function update(Request $request, Olt $olt): JsonResponse
    {
        $data = $request->validate([
            'pop_id' => 'sometimes|exists:pops,id',
            'name' => 'sometimes|string|max:255',
            'ip' => 'nullable|ip',
            'status' => 'nullable|in:online,offline,maintenance',
            'capacity' => 'nullable|integer|min:0',
            'pon_ports' => 'nullable|integer|min:1|max:64',
            'notes' => 'nullable|string',
        ]);

        $olt->update($data);

        return response()->json(['message' => 'OLT berhasil diperbarui.', 'data' => $olt->load('pop:id,name,code')]);
    }

    public function destroy(Olt $olt): JsonResponse
    {
        $olt->delete();

        return response()->json(['message' => 'OLT berhasil dihapus.']);
    }
}
