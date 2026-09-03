<?php

namespace App\Http\Controllers;

use App\Http\Resources\VillageResource;
use App\Models\Village;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    public function index(Request $request)
    {
        $query = Village::query();
        if ($request->filled('kecamatan_id')) {
            $query->where('district_id', $request->kecamatan_id);
        }

        return VillageResource::collection($query->paginate($request->get('per_page', 10)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['kecamatan_id' => 'required|exists:districts,id', 'kode' => 'required|string|unique:villages,code', 'nama' => 'required|string|max:255', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string']);
        $village = Village::create(['district_id' => $validated['kecamatan_id'], 'code' => $validated['kode'], 'name' => $validated['nama'], 'latitude' => $validated['latitude'] ?? null, 'longitude' => $validated['longitude'] ?? null, 'geometry' => $validated['geometry'] ?? null]);

        return new VillageResource($village);
    }

    public function show($id)
    {
        return new VillageResource(Village::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $village = Village::findOrFail($id);
        $validated = $request->validate(['kecamatan_id' => 'sometimes|required|exists:districts,id', 'kode' => 'sometimes|required|string|unique:villages,code,'.$village->id, 'nama' => 'sometimes|required|string|max:255', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string']);
        if (isset($validated['kecamatan_id'])) {
            $village->district_id = $validated['kecamatan_id'];
        }
        if (isset($validated['kode'])) {
            $village->code = $validated['kode'];
        }
        if (isset($validated['nama'])) {
            $village->name = $validated['nama'];
        }
        if (isset($validated['latitude'])) {
            $village->latitude = $validated['latitude'];
        }
        if (isset($validated['longitude'])) {
            $village->longitude = $validated['longitude'];
        }
        if (isset($validated['geometry'])) {
            $village->geometry = $validated['geometry'];
        }
        $village->save();

        return new VillageResource($village);
    }

    public function destroy($id)
    {
        Village::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
