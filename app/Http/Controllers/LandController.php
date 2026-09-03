<?php

namespace App\Http\Controllers;

use App\Http\Resources\LandResource;
use App\Models\Land;
use Illuminate\Http\Request;

class LandController extends Controller
{
    public function index(Request $request)
    {
        $query = Land::query();
        if ($request->filled('desa_id')) {
            $query->where('village_id', $request->desa_id);
        }

        return LandResource::collection($query->paginate($request->get('per_page', 10)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['desa_id' => 'required|exists:villages,id', 'kode' => 'required|string|unique:lands,code', 'nama' => 'required|string|max:255', 'luas_lahan' => 'nullable|numeric', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        $status_val = (isset($validated['status']) && $validated['status'] == 'tidak_aktif') ? 'inactive' : 'active';
        $land = Land::create(['village_id' => $validated['desa_id'], 'code' => $validated['kode'], 'name' => $validated['nama'], 'area' => $validated['luas_lahan'] ?? null, 'latitude' => $validated['latitude'] ?? null, 'longitude' => $validated['longitude'] ?? null, 'geometry' => $validated['geometry'] ?? null, 'status' => $status_val]);

        return new LandResource($land);
    }

    public function show($id)
    {
        return new LandResource(Land::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $land = Land::findOrFail($id);
        $validated = $request->validate(['desa_id' => 'sometimes|required|exists:villages,id', 'kode' => 'sometimes|required|string|unique:lands,code,'.$land->id, 'nama' => 'sometimes|required|string|max:255', 'luas_lahan' => 'nullable|numeric', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        if (isset($validated['desa_id'])) {
            $land->village_id = $validated['desa_id'];
        }
        if (isset($validated['kode'])) {
            $land->code = $validated['kode'];
        }
        if (isset($validated['nama'])) {
            $land->name = $validated['nama'];
        }
        if (isset($validated['luas_lahan'])) {
            $land->area = $validated['luas_lahan'];
        }
        if (isset($validated['latitude'])) {
            $land->latitude = $validated['latitude'];
        }
        if (isset($validated['longitude'])) {
            $land->longitude = $validated['longitude'];
        }
        if (isset($validated['geometry'])) {
            $land->geometry = $validated['geometry'];
        }
        if (isset($validated['status'])) {
            $land->status = $validated['status'] == 'tidak_aktif' ? 'inactive' : 'active';
        }
        $land->save();

        return new LandResource($land);
    }

    public function destroy($id)
    {
        Land::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
