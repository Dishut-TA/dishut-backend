<?php

namespace App\Http\Controllers;

use App\Http\Resources\InterventionTypeResource;
use App\Models\InterventionType;
use Illuminate\Http\Request;

class InterventionTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = InterventionType::query();
        if ($request->filled('status')) {
            $query->where('status', $request->status == 'tidak_aktif' ? 'inactive' : 'active');
        }

        return InterventionTypeResource::collection($query->paginate($request->get('per_page', 10)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['kode' => 'required|string|unique:intervention_types,code', 'nama' => 'required|string|max:255', 'deskripsi' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        $status_val = (isset($validated['status']) && $validated['status'] == 'tidak_aktif') ? 'inactive' : 'active';
        $intervention = InterventionType::create(['code' => $validated['kode'], 'name' => $validated['nama'], 'description' => $validated['deskripsi'] ?? null, 'status' => $status_val]);

        return new InterventionTypeResource($intervention);
    }

    public function show($id)
    {
        return new InterventionTypeResource(InterventionType::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $intervention = InterventionType::findOrFail($id);
        $validated = $request->validate(['kode' => 'sometimes|required|string|unique:intervention_types,code,'.$intervention->id, 'nama' => 'sometimes|required|string|max:255', 'deskripsi' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        if (isset($validated['kode'])) {
            $intervention->code = $validated['kode'];
        }
        if (isset($validated['nama'])) {
            $intervention->name = $validated['nama'];
        }
        if (isset($validated['deskripsi'])) {
            $intervention->description = $validated['deskripsi'];
        }
        if (isset($validated['status'])) {
            $intervention->status = $validated['status'] == 'tidak_aktif' ? 'inactive' : 'active';
        }
        $intervention->save();

        return new InterventionTypeResource($intervention);
    }

    public function destroy($id)
    {
        InterventionType::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
