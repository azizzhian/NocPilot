<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pop::query()->withCount(['odcs', 'olts'])->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('area', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:pops,code',
            'area' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'capacity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $pop = Pop::create($data);

        return response()->json(['message' => 'POP berhasil ditambahkan.', 'data' => $pop], 201);
    }

    public function show(Pop $pop): JsonResponse
    {
        return response()->json(['data' => $pop->loadCount(['odcs', 'olts'])]);
    }

    public function update(Request $request, Pop $pop): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:pops,code,'.$pop->id,
            'area' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'capacity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $pop->update($data);

        return response()->json(['message' => 'POP berhasil diperbarui.', 'data' => $pop]);
    }

    public function destroy(Pop $pop): JsonResponse
    {
        $pop->delete();

        return response()->json(['message' => 'POP berhasil dihapus.']);
    }
}
