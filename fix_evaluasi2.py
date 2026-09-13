import re

file_path = r"app\Http\Controllers\Api\PenugasanEvaluasiController.php"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

target = """    public function kalkulasiEvaluasi(Request $request, $id): JsonResponse
    {
        $evaluasi = Evaluasi::findOrFail($id);
        $evaluasi->status = 'Selesai Evaluasi';
        
        // PERBAIKAN: Simpan persentase_tumbuh jika ada
        if ($request->has('persentase_tumbuh')) {
            $evaluasi->persentase_tumbuh = $request->persentase_tumbuh;
        }
        
        $evaluasi->save();

        return response()->json(['message' => 'Evaluasi berhasil dikalkulasi', 'data' => $evaluasi]);
    }"""

replacement = """    public function kalkulasiEvaluasi(Request $request, $id): JsonResponse
    {
        $evaluasi = Evaluasi::findOrFail($id);
        $evaluasi->status = 'Selesai Evaluasi';
        
        // PERBAIKAN: Simpan persentase_tumbuh jika ada
        if ($request->has('persentase_tumbuh')) {
            $evaluasi->persentase_tumbuh = $request->persentase_tumbuh;

            // Jika persentase tumbuh >= 75%, program di modul pelaksanaan monitoring berubah statusnya menjadi 'Monitoring Selesai'
            if ($request->persentase_tumbuh >= 75) {
                \App\Models\Penugasan::where('penugasanable_type', $evaluasi->evaluable_type)
                    ->where('penugasanable_id', $evaluasi->evaluable_id)
                    ->where('jenis_kegiatan', 'Monitoring')
                    ->update(['status' => 'Monitoring Selesai']);
            }
        }
        
        $evaluasi->save();

        return response()->json(['message' => 'Evaluasi berhasil dikalkulasi', 'data' => $evaluasi]);
    }"""

if target in content:
    content = content.replace(target, replacement)
    with open(file_path, "w", encoding="utf-8") as f:
        f.write(content)
    print("Updated successfully")
else:
    print("Target not found")
