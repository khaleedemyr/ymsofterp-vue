<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ONLYOFFICE SERVER TEST ===\n";

// Get OnlyOffice URL from config
$onlyofficeUrl = config('app.onlyoffice_url', 'http://localhost:80');
echo "OnlyOffice URL: $onlyofficeUrl\n";

// Test API endpoint
$apiUrl = $onlyofficeUrl . '/web-apps/apps/api/documents/api.js';
echo "API URL: $apiUrl\n";

// Test if server is reachable
echo "\n=== CONNECTIVITY TEST ===\n";

// Test with cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response: " . substr($response, 0, 200) . "...\n";
}

// Test with file_get_contents
echo "\n=== ALTERNATIVE TEST ===\n";
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'method' => 'GET'
    ]
]);

$content = @file_get_contents($apiUrl, false, $context);
if ($content === false) {
    echo "file_get_contents failed\n";
    $headers = $http_response_header ?? [];
    echo "Headers: " . implode(', ', $headers) . "\n";
} else {
    echo "file_get_contents success\n";
    echo "Content length: " . strlen($content) . " bytes\n";
}

// Test document download URL
echo "\n=== DOCUMENT DOWNLOAD TEST ===\n";
$doc = \App\Models\SharedDocument::first();
if ($doc) {
    $downloadUrl = "http://localhost:8000/shared-documents/{$doc->id}/download";
    echo "Download URL: $downloadUrl\n";
    
    // Test download URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "Download HTTP Code: $httpCode\n";
    if ($error) {
        echo "Download cURL Error: $error\n";
    } else {
        echo "Download Response: " . substr($response, 0, 200) . "...\n";
    }
}

// Show configuration
echo "\n=== CONFIGURATION ===\n";
echo "APP_URL: " . config('app.url') . "\n";
echo "ONLYOFFICE_URL: " . config('app.onlyoffice_url') . "\n";

// Test environment variables
echo "\n=== ENVIRONMENT ===\n";
echo "ONLYOFFICE_URL env: " . (env('ONLYOFFICE_URL') ?: 'NOT SET') . "\n";
echo "APP_URL env: " . (env('APP_URL') ?: 'NOT SET') . "\n";

// Show OnlyOffice config for document
if ($doc) {
    echo "\n=== ONLYOFFICE CONFIG FOR DOCUMENT ===\n";
    $config = [
        'document' => [
            'fileType' => $doc->file_type,
            'key' => $doc->id,
            'title' => $doc->title,
            'url' => "http://localhost:8000/shared-documents/{$doc->id}/download"
        ],
        'documentType' => in_array($doc->file_type, ['xlsx', 'xls']) ? 'spreadsheet' : 'text',
        'editorConfig' => [
            'mode' => 'edit',
            'callbackUrl' => "http://localhost:8000/shared-documents/{$doc->id}/callback"
        ]
    ];
    
    echo json_encode($config, JSON_PRETTY_PRINT) . "\n";
} 