<?php

namespace App\Http\Controllers;

use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $query = City::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', '%'.$request->search.'%');
        }

        return CityResource::collection($query->paginate($request->get('per_page', 10)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['kode' => 'required|string|unique:cities,code', 'nama' => 'required|string|max:255', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string']);
        $city = City::create(['code' => $validated['kode'], 'name' => $validated['nama'], 'latitude' => $validated['latitude'] ?? null, 'longitude' => $validated['longitude'] ?? null, 'geometry' => $validated['geometry'] ?? null]);

        return new CityResource($city);
    }

    public function show($id)
    {
        return new CityResource(City::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $city = City::findOrFail($id);
        $validated = $request->validate(['kode' => 'sometimes|required|string|unique:cities,code,'.$city->id, 'nama' => 'sometimes|required|string|max:255', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'geometry' => 'nullable|string']);
        if (isset($validated['kode'])) {
            $city->code = $validated['kode'];
        }
        if (isset($validated['nama'])) {
            $city->name = $validated['nama'];
        }
        if (isset($validated['latitude'])) {
            $city->latitude = $validated['latitude'];
        }
        if (isset($validated['longitude'])) {
            $city->longitude = $validated['longitude'];
        }
        if (isset($validated['geometry'])) {
            $city->geometry = $validated['geometry'];
        }
        $city->save();

        return new CityResource($city);
    }

    public function destroy($id)
    {
        City::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
