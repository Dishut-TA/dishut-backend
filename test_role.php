<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::latest()->first();
dump($user->roles->pluck('name'));
dump($user->hasRole('csr'));
dump($user->role);
