import os

files = [
    r"app\Http\Controllers\Api\PenghentianPendanaanCsrController.php",
    r"app\Http\Controllers\Api\TransaksiCsrController.php"
]

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    # handle PenghentianPendanaanCsrController
    if "hasRole(['csr', 'CSR'])" in content:
        content = content.replace("role === 'csr' || $user->hasRole(['csr', 'CSR'])", "role === 'csr' || $user->role === 'CSR' || $user->hasRole(['csr', 'CSR'])")
    elif "hasRole('csr')" in content:
        content = content.replace("role === 'csr' || $user->hasRole('csr')", "role === 'csr' || $user->role === 'CSR' || $user->hasRole(['csr', 'CSR'])")
        content = content.replace("role === 'csr' || $user->hasRole(['csr', 'CSR'])", "role === 'csr' || $user->role === 'CSR' || $user->hasRole(['csr', 'CSR'])")

    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f"Updated {file}")
