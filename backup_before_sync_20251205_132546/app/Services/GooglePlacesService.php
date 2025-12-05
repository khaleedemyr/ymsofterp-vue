<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GooglePlacesService
{
    protected $apiKey;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    public function __construct()
    {
        $this->apiKey = config('services.google.places_api_key');
    }

    public function getPlaceDetails($placeId)
    {
        $cacheKey = "place_details_{$placeId}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($placeId) {
            $response = Http::get("{$this->baseUrl}/details/json", [
                'place_id' => $placeId,
                'fields' => 'name,rating,reviews,formatted_address,geometry',
                'key' => $this->apiKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'OK') {
                    return $this->formatPlaceDetails($data['result']);
                }
            }

            throw new \Exception('Failed to fetch place details: ' . ($data['status'] ?? 'Unknown error'));
        });
    }

    public function getPlaceIdFromUrl($url)
    {
        // Clean URL first
        $url = $this->cleanUrl($url);
        
        // Try to get place ID from URL
        $placeId = $this->extractPlaceId($url);
        
        if ($placeId) {
            return $placeId;
        }

        // If no place ID found, try to get it from coordinates
        $coordinates = $this->extractCoordinates($url);
        if ($coordinates) {
            return $this->getPlaceIdFromCoordinates($coordinates['lat'], $coordinates['lng']);
        }

        throw new \Exception('Could not extract place ID or coordinates from URL');
    }

    protected function cleanUrl($url)
    {
        // Decode URL
        $url = urldecode($url);
        
        // Remove whitespace
        $url = preg_replace('/\s+/', '', $url);
        
        // Fix common encoding issues
        $url = str_replace([' 2F', ' 3D', '2F', '3D'], ['/', '=', '/', '='], $url);
        
        // Remove invalid characters
        $url = preg_replace('/[^a-zA-Z0-9\-\_\.\:\/\?\=\&]/', '', $url);
        
        return $url;
    }

    protected function extractPlaceId($url)
    {
        // Try to get place ID from URL
        if (preg_match('/place\/([^\/]+)/', $url, $matches)) {
            return $matches[1];
        }

        // If URL is a short URL, follow redirects
        if (Str::contains($url, ['goo.gl', 'maps.app.goo.gl'])) {
            try {
                $response = Http::get($url);
                if ($response->successful()) {
                    $finalUrl = $response->effectiveUri();
                    if (preg_match('/place\/([^\/]+)/', $finalUrl, $matches)) {
                        return $matches[1];
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error following redirect: ' . $e->getMessage());
            }
        }

        return null;
    }

    protected function extractCoordinates($url)
    {
        // Try to get coordinates from URL
        if (preg_match('/@([-\d\.]+),([-\d\.]+)/', $url, $matches)) {
            return [
                'lat' => $matches[1],
                'lng' => $matches[2]
            ];
        }

        return null;
    }

    protected function getPlaceIdFromCoordinates($lat, $lng)
    {
        // Use Places API to get place ID from coordinates
        $response = Http::get("{$this->baseUrl}/nearbysearch/json", [
            'location' => "{$lat},{$lng}",
            'radius' => 50, // 50 meters
            'key' => $this->apiKey
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['status'] === 'OK' && !empty($data['results'])) {
                return $data['results'][0]['place_id'];
            }
        }

        throw new \Exception('Could not find place ID from coordinates');
    }

    protected function formatPlaceDetails($place)
    {
        $reviews = collect($place['reviews'] ?? [])->map(function ($review) {
            return [
                'author' => $review['author_name'] ?? '',
                'rating' => (string)($review['rating'] ?? ''),
                'date' => $review['relative_time_description'] ?? '',
                'text' => $review['text'] ?? '',
                'profile_photo' => $review['profile_photo_url'] ?? '',
                'time' => $review['time'] ?? 0
            ];
        })->values()->all();

        return [
            'name' => $place['name'] ?? '',
            'address' => $place['formatted_address'] ?? '',
            'rating' => $place['rating'] ?? 0,
            'location' => [
                'lat' => $place['geometry']['location']['lat'] ?? 0,
                'lng' => $place['geometry']['location']['lng'] ?? 0
            ],
            'reviews' => $reviews
        ];
    }
} 