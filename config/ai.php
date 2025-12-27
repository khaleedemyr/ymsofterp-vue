<?php

return [
    'gemini' => [
        'api_key' => env('GOOGLE_GEMINI_API_KEY', 'AIzaSyCMNGsLJ7RPH-2b9oK_pFjJmYHUx-KXX1k'),
        'model' => 'gemini-2.5-flash', // Updated: gemini-pro deprecated, using gemini-2.5-flash
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ],
];

