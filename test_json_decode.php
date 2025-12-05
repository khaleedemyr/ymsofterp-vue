<?php

/**
 * JSON Decode Test Script
 * 
 * This script tests JSON decoding functionality for LMS file paths.
 * Run this to verify JSON handling works correctly.
 */

echo "=== JSON DECODE TEST SCRIPT ===\n";
echo "Testing JSON decoding functionality...\n\n";

// Test 1: Normal JSON array
echo "1. Testing normal JSON array:\n";
$testJson1 = '["file1.pdf", "file2.docx", "file3.jpg"]';
echo "   Input: {$testJson1}\n";

$decoded1 = json_decode($testJson1, true);
echo "   Decoded: ";
print_r($decoded1);
echo "   JSON Error: " . json_last_error_msg() . "\n";
echo "   Is Array: " . (is_array($decoded1) ? 'YES' : 'NO') . "\n\n";

// Test 2: JSON with file types
echo "2. Testing JSON with file types:\n";
$testJson2 = '["pdf", "document", "image"]';
echo "   Input: {$testJson2}\n";

$decoded2 = json_decode($testJson2, true);
echo "   Decoded: ";
print_r($decoded2);
echo "   JSON Error: " . json_last_error_msg() . "\n";
echo "   Is Array: " . (is_array($decoded2) ? 'YES' : 'NO') . "\n\n";

// Test 3: Invalid JSON
echo "3. Testing invalid JSON:\n";
$testJson3 = '["file1.pdf", "file2.docx", "file3.jpg"'; // Missing closing bracket
echo "   Input: {$testJson3}\n";

$decoded3 = json_decode($testJson3, true);
echo "   Decoded: ";
var_dump($decoded3);
echo "   JSON Error: " . json_last_error_msg() . "\n";
echo "   Is Array: " . (is_array($decoded3) ? 'YES' : 'NO') . "\n\n";

// Test 4: Empty JSON
echo "4. Testing empty JSON:\n";
$testJson4 = '[]';
echo "   Input: {$testJson4}\n";

$decoded4 = json_decode($testJson4, true);
echo "   Decoded: ";
print_r($decoded4);
echo "   JSON Error: " . json_last_error_msg() . "\n";
echo "   Is Array: " . (is_array($decoded4) ? 'YES' : 'NO') . "\n";
echo "   Count: " . count($decoded4) . "\n\n";

// Test 5: Single file JSON
echo "5. Testing single file JSON:\n";
$testJson5 = '["single_file.pdf"]';
echo "   Input: {$testJson5}\n";

$decoded5 = json_decode($testJson5, true);
echo "   Decoded: ";
print_r($decoded5);
echo "   JSON Error: " . json_last_error_msg() . "\n";
echo "   Is Array: " . (is_array($decoded5) ? 'YES' : 'NO') . "\n";
echo "   Count: " . count($decoded5) . "\n\n";

// Test 6: Complex JSON with mixed types
echo "6. Testing complex JSON:\n";
$testJson6 = '["file1.pdf", "file2.docx", "file3.jpg", "file4.mp4"]';
$testTypes6 = '["pdf", "document", "image", "video"]';
echo "   Files: {$testJson6}\n";
echo "   Types: {$testTypes6}\n";

$decodedFiles6 = json_decode($testJson6, true);
$decodedTypes6 = json_decode($testTypes6, true);

echo "   Decoded Files: ";
print_r($decodedFiles6);
echo "   Decoded Types: ";
print_r($decodedTypes6);

if (is_array($decodedFiles6) && is_array($decodedTypes6)) {
    echo "   Processing files:\n";
    foreach ($decodedFiles6 as $index => $file) {
        $type = $decodedTypes6[$index] ?? 'unknown';
        echo "     File {$index}: {$file} (Type: {$type})\n";
    }
}

echo "\n=== JSON DECODE TEST COMPLETED ===\n";
