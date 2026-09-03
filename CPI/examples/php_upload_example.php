<?php
// Contoh PHP native/cURL. Di Laravel, konsepnya sama: kirim multipart ke Python service.

$pythonUrl = 'http://127.0.0.1:8000/analysis';

$postFields = [
    'project_id' => 'project_1',
    'target_resolution' => '100',
    'zone_api_url' => 'http://localhost/api/zones',
    'history_api_url' => 'http://localhost/api/interventions/{zone_id}',
    'dem' => new CURLFile('/path/storage/project_1/DEM.tif'),
    'landcover' => new CURLFile('/path/storage/project_1/landcover.zip'),
    'rainfall' => new CURLFile('/path/storage/project_1/rainfall.tif'),
    'soil' => new CURLFile('/path/storage/project_1/soil.zip'),
    'das' => new CURLFile('/path/storage/project_1/das.zip'),
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $pythonUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 0,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error) {
    throw new Exception($error);
}

if ($httpCode >= 400) {
    throw new Exception($response);
}

$result = json_decode($response, true);

// Simpan $result['job_id'], $result['map'], $result['table'], dan $result['ahp'] ke database PHP.
print_r($result);
