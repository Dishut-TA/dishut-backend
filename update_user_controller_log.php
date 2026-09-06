<?php
$file = __DIR__ . '/app/Http/Controllers/Api/UserController.php';
$content = file_get_contents($file);
$content = str_replace("public function store(Request \$request)\n    {", "public function store(Request \$request)\n    {\n        \Illuminate\Support\Facades\Log::info('Store User Request:', \$request->all());", $content);
file_put_contents($file, $content);
echo "UserController log added";
