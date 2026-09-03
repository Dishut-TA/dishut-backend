<?php

namespace App\Http\Controllers;

use App\Http\Resources\InterventionRecommendationResource;
use App\Models\InterventionRecommendation;
use Illuminate\Http\Request;

class InterventionRecommendationController extends Controller
{
    public function index(Request $request)
    {
        $query = InterventionRecommendation::query();
        if ($request->filled('jenis_intervensi_id')) {
            $query->where('intervention_type_id', $request->jenis_intervensi_id);
        }

        return InterventionRecommendationResource::collection($query->paginate($request->get('per_page', 10)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['jenis_intervensi_id' => 'required|exists:intervention_types,id', 'nama' => 'required|string|max:255', 'deskripsi' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        $status_val = (isset($validated['status']) && $validated['status'] == 'tidak_aktif') ? 'inactive' : 'active';
        $rekomendasi = InterventionRecommendation::create(['intervention_type_id' => $validated['jenis_intervensi_id'], 'name' => $validated['nama'], 'description' => $validated['deskripsi'] ?? null, 'status' => $status_val]);

        return new InterventionRecommendationResource($rekomendasi);
    }

    public function show($id)
    {
        return new InterventionRecommendationResource(InterventionRecommendation::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $rekomendasi = InterventionRecommendation::findOrFail($id);
        $validated = $request->validate(['jenis_intervensi_id' => 'sometimes|required|exists:intervention_types,id', 'nama' => 'sometimes|required|string|max:255', 'deskripsi' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        if (isset($validated['jenis_intervensi_id'])) {
            $rekomendasi->intervention_type_id = $validated['jenis_intervensi_id'];
        }
        if (isset($validated['nama'])) {
            $rekomendasi->name = $validated['nama'];
        }
        if (isset($validated['deskripsi'])) {
            $rekomendasi->description = $validated['deskripsi'];
        }
        if (isset($validated['status'])) {
            $rekomendasi->status = $validated['status'] == 'tidak_aktif' ? 'inactive' : 'active';
        }
        $rekomendasi->save();

        return new InterventionRecommendationResource($rekomendasi);
    }

    public function destroy($id)
    {
        InterventionRecommendation::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
