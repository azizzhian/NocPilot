<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternetPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternetPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InternetPackage::query()->orderBy('speed_mbps');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'speed_mbps' => 'required|integer|min:1',
            'price' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $package = InternetPackage::create($data);

        return response()->json(['message' => 'Paket berhasil ditambahkan.', 'data' => $package], 201);
    }

    public function show(InternetPackage $internetPackage): JsonResponse
    {
        return response()->json(['data' => $internetPackage]);
    }

    public function update(Request $request, InternetPackage $internetPackage): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'speed_mbps' => 'sometimes|integer|min:1',
            'price' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $internetPackage->update($data);

        return response()->json(['message' => 'Paket berhasil diperbarui.', 'data' => $internetPackage]);
    }

    public function destroy(InternetPackage $internetPackage): JsonResponse
    {
        $internetPackage->delete();

        return response()->json(['message' => 'Paket berhasil dihapus.']);
    }
}
