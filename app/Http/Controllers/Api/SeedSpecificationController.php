<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SeedSpecification;
use App\Http\Resources\SeedSpecificationResource;

class SeedSpecificationController extends Controller
{
    public function index()
    {
        return SeedSpecificationResource::collection(SeedSpecification::with('seed')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Add basic validation rules here
        ]);
        // For simplicity in testing, we use all()
        $item = SeedSpecification::create($request->all());
        return new SeedSpecificationResource($item);
    }

    public function show(string $id)
    {
        $item = SeedSpecification::with('seed')->findOrFail($id);
        return new SeedSpecificationResource($item);
    }

    public function getById(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $item = SeedSpecification::findOrFail($id);
        $item->update($request->all());
        return new SeedSpecificationResource($item);
    }

    public function destroy(string $id)
    {
        $item = SeedSpecification::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}