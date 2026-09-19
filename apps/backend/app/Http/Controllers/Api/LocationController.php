<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Location::query()
            ->with('odc:id,name,code')
            ->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($odcId = $request->integer('odc_id')) {
            $query->where('odc_id', $odcId);
        }

        if ($status = trim($request->string('status')->toString())) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(min(max($request->integer('per_page', 20), 1), 200)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:locations,name',
            'odc_id' => 'nullable|exists:odcs,id',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $location = Location::create($data);

        return response()->json([
            'message' => 'Lokasi berhasil ditambahkan.',
            'data' => $location->load('odc:id,name,code'),
        ], 201);
    }

    public function show(Location $location): JsonResponse
    {
        return response()->json(['data' => $location->load('odc:id,name,code')]);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255|unique:locations,name,'.$location->id,
            'odc_id' => 'nullable|exists:odcs,id',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $location->update($data);

        return response()->json([
            'message' => 'Lokasi berhasil diperbarui.',
            'data' => $location->load('odc:id,name,code'),
        ]);
    }

    public function destroy(Location $location): JsonResponse
    {
        $location->delete();

        return response()->json(['message' => 'Lokasi berhasil dihapus.']);
    }
}
