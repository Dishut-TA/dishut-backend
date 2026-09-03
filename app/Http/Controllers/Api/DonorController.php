<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Donor;
use App\Http\Resources\DonorResource;

class DonorController extends Controller
{
    public function index()
    {
        return DonorResource::collection(Donor::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Add basic validation rules here
        ]);
        // For simplicity in testing, we use all()
        $item = Donor::create($request->all());
        return new DonorResource($item);
    }

    public function show(string $id)
    {
        $item = Donor::findOrFail($id);
        return new DonorResource($item);
    }

    public function getById(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $item = Donor::findOrFail($id);
        $item->update($request->all());
        return new DonorResource($item);
    }

    public function destroy(string $id)
    {
        $item = Donor::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}