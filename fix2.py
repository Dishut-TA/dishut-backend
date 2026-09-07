import os

file_path = r"app\Http\Controllers\Api\PenghentianPendanaanCsrController.php"

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

search_str = "'persentase_tumbuh' => $persentase,"
replace_str = "'persentase_tumbuh_terakhir' => $program->persentase_tumbuh_terakhir ?? $persentase,"

content = content.replace(search_str, replace_str)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated PenghentianPendanaanCsrController.php")
