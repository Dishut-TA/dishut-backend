import os

file_path = r"app\Http\Controllers\Api\PenghentianPendanaanCsrController.php"

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# We need to add documentation fetch inside hasilEvaluasi
search_str = "'penghentian' => $program->sudahDihentikan() ? ["
replace_str = """'dokumentasi' => \\App\\Models\\DokumentasiPenugasan::whereHas('penugasan', function($q) use ($program) {
                    $q->where('penugasanable_id', $program->id)
                      ->where('penugasanable_type', \\App\\Models\\ProgramCsr::class);
                })->get(),
                'penghentian' => $program->sudahDihentikan() ? ["""

content = content.replace(search_str, replace_str)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated PenghentianPendanaanCsrController.php")
