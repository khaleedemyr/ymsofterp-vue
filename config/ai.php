<?php

return [
    'gemini' => [
        'api_key' => env('GOOGLE_GEMINI_API_KEY', 'AIzaSyCMNGsLJ7RPH-2b9oK_pFjJmYHUx-KXX1k'),
        // Upgrade ke model yang lebih powerful untuk analisis kompleks
        'model' => env('GEMINI_MODEL', 'gemini-1.5-pro'), // gemini-1.5-pro untuk analisis kompleks, atau gemini-1.5-flash untuk lebih cepat
        'temperature' => 0.7,
        'max_tokens' => 4000, // Increased untuk analisis yang lebih detail
    ],
    
    // OpenAI sebagai alternatif (lebih powerful untuk analisis kompleks)
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', null),
        'model' => env('OPENAI_MODEL', 'gpt-4o'), // gpt-4o untuk analisis terbaik, atau gpt-4o-mini untuk lebih murah
        'temperature' => 0.7,
        'max_tokens' => 4000,
    ],
    
    // Anthropic Claude sebagai alternatif
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY', null),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'), // Claude Sonnet 4.5 (latest) untuk analisis terbaik
        'temperature' => 0.7,
        'max_tokens' => 8192, // Increased untuk analisis yang lebih lengkap (Claude Sonnet 4.5 max output: 8192 tokens)
        'budget_limit' => env('CLAUDE_BUDGET_LIMIT', 2000000), // Default Rp 2 juta per bulan
        'pricing' => [
            'input' => 3.00, // USD per 1M tokens
            'output' => 15.00, // USD per 1M tokens
        ],
    ],
    
    // USD to Rupiah rate (untuk konversi biaya)
    'usd_to_rupiah_rate' => env('USD_TO_RUPIAH_RATE', 15000),
    
    // Provider yang digunakan (gemini, openai, atau claude)
    'provider' => env('AI_PROVIDER', 'gemini'),
    
    // External data sources
    'external_data' => [
        'enabled' => env('AI_EXTERNAL_DATA_ENABLED', true),
        'weather_api_key' => env('WEATHER_API_KEY', null), // OpenWeatherMap API
        'news_api_key' => env('NEWS_API_KEY', null), // NewsAPI.org
    ],
];

