import re

file_path = r"app\Http\Controllers\Api\PenugasanController.php"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Let's cleanly replace the target section.
# First find the start of the source checking
start_pattern = "if ($source instanceof DonationProgram) {"
# Find the end of it
end_pattern = "$wilayah = $source->kabupaten ?? '-';\n            }"

if start_pattern in content and end_pattern in content:
    start_idx = content.find(start_pattern)
    end_idx = content.find(end_pattern) + len(end_pattern)
    
    original_block = content[start_idx:end_idx]
    
    new_block = original_block + """

            // Hitung realisasi tanaman aktual dari DataTanaman (Petak Ukur -> Data Tanaman)
            $realisasiTanaman = 0;
            $penugasanTerkait = $penugasans->where('penugasanable_type', $p->penugasanable_type)
                                           ->where('penugasanable_id', $p->penugasanable_id)
                                           ->where('jenis_kegiatan', 'Pelaksanaan Penanaman');
            foreach ($penugasanTerkait as $pt) {
                if ($pt->petakUkurs) {
                    foreach ($pt->petakUkurs as $petak) {
                        if ($petak->dataTanamans) {
                            $realisasiTanaman += $petak->dataTanamans->sum('jumlah');
                        }
                    }
                }
            }
            if ($realisasiTanaman > 0) {
                $realisasiBibit = $realisasiTanaman;
            }"""
    
    # We might have already inserted it in fix_dashboard.py
    if "// Hitung realisasi tanaman aktual" not in content:
        content = content.replace(original_block, new_block)
        
with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)
print("Updated PenugasanController successfully")
