<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlantedSeed;
use App\Http\Resources\PlantedSeedResource;

class PlantedSeedController extends Controller
{
    public function index()
    {
        return PlantedSeedResource::collection(PlantedSeed::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Add basic validation rules here
        ]);
        // For simplicity in testing, we use all()
        $item = PlantedSeed::create($request->all());
        return new PlantedSeedResource($item);
    }

    public function show(string $id)
    {
        $item = PlantedSeed::findOrFail($id);
        return new PlantedSeedResource($item);
    }

    public function getById(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $item = PlantedSeed::findOrFail($id);
        $item->update($request->all());
        return new PlantedSeedResource($item);
    }

    public function destroy(string $id)
    {
        $item = PlantedSeed::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}