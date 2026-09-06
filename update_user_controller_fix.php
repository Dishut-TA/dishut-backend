<?php
$file = __DIR__ . '/app/Http/Controllers/Api/UserController.php';
$content = file_get_contents($file);

$oldUpdateSave = "\$user->update(\$userData);";
$newUpdateSave = "if (array_key_exists('kth_id', \$validated)) {\n                \$userData['kth_id'] = \$validated['kth_id'];\n            }\n\n            \$user->update(\$userData);";
$content = str_replace($oldUpdateSave, $newUpdateSave, $content);

file_put_contents($file, $content);
echo "UserController update fixed";
