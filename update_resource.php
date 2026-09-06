<?php
$file = __DIR__ . '/app/Http/Resources/UserResource.php';
$content = file_get_contents($file);
$replacement = "'profil' => new PegawaiResource(\$this->whenLoaded('pegawai')),\n            'kth_id' => \$this->kth_id,\n            'kth' => \$this->whenLoaded('kth'),";
$content = preg_replace("/'profil' => new PegawaiResource\(\\\$this->whenLoaded\('pegawai'\)\),/", $replacement, $content);
file_put_contents($file, $content);

$authFile = __DIR__ . '/app/Http/Controllers/Api/AuthController.php';
$authContent = file_get_contents($authFile);
$authContent = str_replace("\$user->load('pegawai');", "\$user->load(['pegawai', 'kth', 'roles.permissions']);", $authContent);
file_put_contents($authFile, $authContent);

echo "Done";
