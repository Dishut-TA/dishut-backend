<?php
$file = __DIR__ . '/app/Http/Controllers/Api/UserController.php';
$content = file_get_contents($file);

// Store method validation
$oldStoreVal = "'peran.*' => 'string|exists:roles,name',";
$newStoreVal = "'peran.*' => 'string|exists:roles,name',\n                'kth_id' => 'nullable|exists:kths,id',";
$content = str_replace($oldStoreVal, $newStoreVal, $content);

// Store method create
$oldStoreCreate = "User::create([\n                'username' => \$validated['nama_pengguna'],\n                'email' => \$validated['email'],\n                'password' => Hash::make(\$validated['kata_sandi']),\n            ]);";
$newStoreCreate = "User::create([\n                'username' => \$validated['nama_pengguna'],\n                'email' => \$validated['email'],\n                'password' => Hash::make(\$validated['kata_sandi']),\n                'kth_id' => \$validated['kth_id'] ?? null,\n            ]);";
$content = str_replace($oldStoreCreate, $newStoreCreate, $content);

// Update method validation - is same as store mostly, but let's just make sure both occur.
// Actually since oldStoreVal is a generic string, it might match both store and update!
// But wait, the create block is specific to store.

// Update method save
$oldUpdateSave = "\$user->update([\n                'username' => \$validated['nama_pengguna'],\n                'email' => \$validated['email'],\n            ]);";
$newUpdateSave = "\$userData = [\n                'username' => \$validated['nama_pengguna'],\n                'email' => \$validated['email'],\n            ];\n            if (array_key_exists('kth_id', \$validated)) {\n                \$userData['kth_id'] = \$validated['kth_id'];\n            }\n            \$user->update(\$userData);";
$content = str_replace($oldUpdateSave, $newUpdateSave, $content);

file_put_contents($file, $content);
echo "UserController updated";
