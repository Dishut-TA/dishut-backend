<?php
$userFile = __DIR__ . '/app/Models/User.php';
$content = file_get_contents($userFile);
$content = str_replace("'role',", "'role',\n        'kth_id',", $content);
$content = preg_replace('/public function kth\(\)\s*\{\s*return \$this->hasOne\(Kth::class\);\s*\}/', "public function kth()\n    {\n        return \$this->belongsTo(Kth::class, 'kth_id');\n    }", $content);
file_put_contents($userFile, $content);

$kthFile = __DIR__ . '/app/Models/Kth.php';
$kthContent = file_get_contents($kthFile);
$kthContent = preg_replace('/\s*\'user_id\',/', '', $kthContent);
$kthContent = preg_replace('/public function user\(\)\s*\{\s*return \$this->belongsTo\(User::class\);\s*\}/', "public function user()\n    {\n        return \$this->hasMany(User::class, 'kth_id');\n    }", $kthContent);
file_put_contents($kthFile, $kthContent);
echo "Done";
