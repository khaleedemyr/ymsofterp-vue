<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apify Instagram Scraper (sama token dengan Google Review: APIFY_TOKEN)
    |--------------------------------------------------------------------------
    */
    'actor_id' => env('APIFY_INSTAGRAM_ACTOR', 'apify/instagram-scraper'),

    'process_queue' => env('INSTAGRAM_SCRAPER_QUEUE', 'instagram-scraper'),

    'run_max_wait_seconds' => (int) env('INSTAGRAM_APIFY_MAX_WAIT', 900),

    'posts' => [
        'results_limit' => (int) env('INSTAGRAM_POSTS_RESULTS_LIMIT', 200),
        'only_newer_than' => env('INSTAGRAM_POSTS_NEWER_THAN', '1 year'),
    ],

    'comments' => [
        'results_limit_per_post' => (int) env('INSTAGRAM_COMMENTS_PER_POST', 50),
        'batch_size' => (int) env('INSTAGRAM_COMMENTS_BATCH', 25),
        'only_if_comments_count_positive' => filter_var(
            env('INSTAGRAM_COMMENTS_ONLY_NONZERO', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profil tetap (bukan per outlet). Sesuaikan label & URL.
    | Key dipakai di kolom profile_key di database.
    |--------------------------------------------------------------------------
    */
    'profiles' => [
        'tempayan' => [
            'label' => 'Tempayan Indonesian Bistro',
            'url' => 'https://www.instagram.com/tempayan.bistro/',
        ],
        'justus_steakhouse' => [
            'label' => 'Justus Steakhouse',
            'url' => 'https://www.instagram.com/justussteakhouse/',
        ],
        'justus_burger' => [
            'label' => 'Justus Burger & Steak',
            'url' => 'https://www.instagram.com/justusburgernsteak/',
        ],
        'asian_grill_express' => [
            'label' => 'Asian Grill Express',
            'url' => 'https://www.instagram.com/asiangrillexpress/',
        ],
        'mwlt_steak' => [
            'label' => 'Melt Steak',
            'url' => 'https://www.instagram.com/meltsteak_id/',
        ],
    ],

];
