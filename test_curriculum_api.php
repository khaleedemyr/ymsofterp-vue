<?php
/**
 * Test Curriculum API Endpoint
 * Script untuk testing API curriculum setelah perbaikan
 */

echo "=== Testing Curriculum API Endpoint ===\n\n";

// Test URL
$baseUrl = 'http://localhost:8000';
$courseId = 5; // Sesuai dengan error log yang Anda tunjukkan
$url = "{$baseUrl}/lms/courses/{$courseId}/curriculum";

echo "Testing URL: {$url}\n";
echo "Method: GET\n\n";

// Make HTTP request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Add headers
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: Curriculum-Test-Script/1.0'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

echo "Sending request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Status Code: {$httpCode}\n";

if ($error) {
    echo "cURL Error: {$error}\n";
} else {
    // Split response into headers and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    
    echo "\nResponse Headers:\n";
    echo $responseHeaders;
    
    echo "\nResponse Body:\n";
    echo $responseBody;
    
    // Try to decode JSON response
    $jsonData = json_decode($responseBody, true);
    if ($jsonData) {
        echo "\n\nParsed JSON Response:\n";
        if (isset($jsonData['success'])) {
            echo "Success: " . ($jsonData['success'] ? 'true' : 'false') . "\n";
        }
        if (isset($jsonData['message'])) {
            echo "Message: " . $jsonData['message'] . "\n";
        }
        if (isset($jsonData['curriculum'])) {
            echo "Curriculum Items: " . count($jsonData['curriculum']) . "\n";
        }
        if (isset($jsonData['availableQuizzes'])) {
            echo "Available Quizzes: " . count($jsonData['availableQuizzes']) . "\n";
        }
        if (isset($jsonData['availableQuestionnaires'])) {
            echo "Available Questionnaires: " . count($jsonData['availableQuestionnaires']) . "\n";
        }
    }
}

echo "\n=== Test Completed ===\n";
