<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeedResource;
use App\Models\Seed;
use Illuminate\Http\Request;

class SeedController extends Controller
{
    public function index(Request $request)
    {
        $query = Seed::query();
        if ($request->filled('status')) {
            $query->where('status', $request->status == 'tidak_aktif' ? 'inactive' : 'active');
        }

        $seeds = Seed::with('specifications')->paginate(10);
        return SeedResource::collection($seeds);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['nama' => 'required|string|max:255', 'jenis' => 'nullable|string|max:255', 'kategori' => 'nullable|string|max:255', 'deskripsi' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        $status_val = (isset($validated['status']) && $validated['status'] == 'tidak_aktif') ? 'inactive' : 'active';
        
        $lastSeed = Seed::orderBy('id', 'desc')->first();
        $nextNumber = 1;
        if ($lastSeed && preg_match('/^B-(\d+)$/', $lastSeed->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        $kode = sprintf('B-%03d', $nextNumber);

        $seed = Seed::create(['code' => $kode, 'name' => $validated['nama'], 'type' => $validated['jenis'] ?? null, 'category' => $validated['kategori'] ?? null, 'description' => $validated['deskripsi'] ?? null, 'status' => $status_val]);

        return new SeedResource($seed);
    }

    public function show($id)
    {
        return new SeedResource(Seed::findOrFail($id));
    }

    public function getBibitById($id)
    {
        return $this->show($id);
    }

    public function getById($id)
    {
        return $this->show($id);
    }

    public function update(Request $request, $id)
    {
        $seed = Seed::findOrFail($id);
        $validated = $request->validate(['kode' => 'sometimes|required|string|unique:seeds,code,'.$seed->id, 'nama' => 'sometimes|required|string|max:255', 'jenis' => 'nullable|string|max:255', 'kategori' => 'nullable|string|max:255', 'deskripsi' => 'nullable|string', 'status' => 'nullable|in:aktif,tidak_aktif']);
        if (isset($validated['kode'])) {
            $seed->code = $validated['kode'];
        }
        if (isset($validated['nama'])) {
            $seed->name = $validated['nama'];
        }
        if (isset($validated['jenis'])) {
            $seed->type = $validated['jenis'];
        }
        if (isset($validated['kategori'])) {
            $seed->category = $validated['kategori'];
        }
        if (isset($validated['deskripsi'])) {
            $seed->description = $validated['deskripsi'];
        }
        if (isset($validated['status'])) {
            $seed->status = $validated['status'] == 'tidak_aktif' ? 'inactive' : 'active';
        }
        $seed->save();

        return new SeedResource($seed);
    }

    public function destroy($id)
    {
        Seed::findOrFail($id)->delete();

        return response()->json(['payload' => ['message' => 'Deleted successfully']]);
    }
}
