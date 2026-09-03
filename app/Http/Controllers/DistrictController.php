<?php

namespace App\Http\Controllers;

use App\Http\Resources\DistrictResource;
use App\Models\District;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function index(Request $request)
    {
        $query = District::query();
        if ($request->filled('kabkota_id')) {
            $query->where('city_id', $request->kabkota_id);
        }

        return DistrictResource::collection($query->paginate($request->get('per_page', 10)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['kabkota_id' => 'required|exists:cities,id', 'kode' => 'required|string|unique:districts,code', 'nama' => 'required|string|max:255', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string']);
        $district = District::create(['city_id' => $validated['kabkota_id'], 'code' => $validated['kode'], 'name' => $validated['nama'], 'latitude' => $validated['latitude'] ?? null, 'longitude' => $validated['longitude'] ?? null, 'geometry' => $validated['geometry'] ?? null]);

        return new DistrictResource($district);
    }

    public function show($id)
    {
        return new DistrictResource(District::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $district = District::findOrFail($id);
        $validated = $request->validate(['kabkota_id' => 'sometimes|required|exists:cities,id', 'kode' => 'sometimes|required|string|unique:districts,code,'.$district->id, 'nama' => 'sometimes|required|string|max:255', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string']);
        if (isset($validated['kabkota_id'])) {
            $district->city_id = $validated['kabkota_id'];
        }
        if (isset($validated['kode'])) {
            $district->code = $validated['kode'];
        }
        if (isset($validated['nama'])) {
            $district->name = $validated['nama'];
        }
        if (isset($validated['latitude'])) {
            $district->latitude = $validated['latitude'];
        }
        if (isset($validated['longitude'])) {
            $district->longitude = $validated['longitude'];
        }
        if (isset($validated['geometry'])) {
            $district->geometry = $validated['geometry'];
        }
        $district->save();

        return new DistrictResource($district);
    }

    public function destroy($id)
    {
        District::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
