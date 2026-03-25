<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GooglePlacesService
{
    protected $apiKey;
    protected $legacyBaseUrl = 'https://maps.googleapis.com/maps/api/place';
    protected $placesNewBaseUrl = 'https://places.googleapis.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.google.places_api_key');
    }

    public function getPlaceDetails($placeId)
    {
        if (!is_string($this->apiKey) || trim($this->apiKey) === '') {
            throw new \Exception('Google Places API key is not configured (GOOGLE_PLACES_API_KEY).');
        }

        $cacheKey = "place_details_{$placeId}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($placeId) {
            // Places API (New)
            // https://developers.google.com/maps/documentation/places/web-service/place-details
            $fieldMask = implode(',', [
                'id',
                'displayName',
                'formattedAddress',
                'rating',
                'location',
                'reviews',
            ]);

            $response = $this->placesNewClient()
                ->withHeaders(['X-Goog-FieldMask' => $fieldMask])
                ->get("{$this->placesNewBaseUrl}/places/{$placeId}");

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatPlaceDetailsNew($data ?? []);
            }

            $data = $response->json();
            $status = $data['error']['status'] ?? 'Unknown';
            $message = $data['error']['message'] ?? null;

            \Log::error('GooglePlacesService: places new place details request failed', [
                'http_status' => $response->status(),
                'google_status' => $status,
                'google_error_message' => $message,
                'place_id' => $placeId,
                'verify' => $this->resolveVerifyOption(),
            ]);

            $suffix = $message ? " ({$message})" : '';
            throw new \Exception("Failed to fetch place details: {$status}{$suffix}");
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
        if (!is_string($this->apiKey) || trim($this->apiKey) === '') {
            throw new \Exception('Google Places API key is not configured (GOOGLE_PLACES_API_KEY).');
        }

        // Places API (New) nearby search
        // https://developers.google.com/maps/documentation/places/web-service/search-nearby
        $fieldMask = 'places.id';
        $payload = [
            'locationRestriction' => [
                'circle' => [
                    'center' => [
                        'latitude' => (float)$lat,
                        'longitude' => (float)$lng,
                    ],
                    'radius' => 50.0,
                ],
            ],
        ];

        $response = $this->placesNewClient()
            ->withHeaders(['X-Goog-FieldMask' => $fieldMask])
            ->post("{$this->placesNewBaseUrl}/places:searchNearby", $payload);

        $data = $response->json();
        if ($response->successful()) {
            $places = $data['places'] ?? [];
            if (!empty($places) && !empty($places[0]['id'])) {
                return $places[0]['id'];
            }
        }

        $status = $data['error']['status'] ?? 'Unknown';
        $message = $data['error']['message'] ?? null;
        $suffix = $message ? " ({$message})" : '';
        throw new \Exception("Could not find place ID from coordinates: {$status}{$suffix}");
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

    protected function formatPlaceDetailsNew($place)
    {
        $reviews = collect($place['reviews'] ?? [])->map(function ($review) {
            $author = $review['authorAttribution']['displayName'] ?? ($review['author'] ?? '');
            $rating = $review['rating'] ?? '';
            $date = $review['relativePublishTimeDescription'] ?? '';

            $text = '';
            if (isset($review['text']['text'])) {
                $text = $review['text']['text'];
            } elseif (isset($review['originalText']['text'])) {
                $text = $review['originalText']['text'];
            }

            $profilePhoto = $review['authorAttribution']['photoUri'] ?? '';
            $time = 0;
            if (!empty($review['publishTime'])) {
                $time = strtotime($review['publishTime']) ?: 0;
            }

            return [
                'author' => (string)$author,
                'rating' => (string)$rating,
                'date' => (string)$date,
                'text' => (string)$text,
                'profile_photo' => (string)$profilePhoto,
                'time' => (int)$time,
            ];
        })->values()->all();

        $displayName = $place['displayName']['text'] ?? ($place['displayName'] ?? '');
        $location = $place['location'] ?? [];

        return [
            'name' => (string)$displayName,
            'address' => (string)($place['formattedAddress'] ?? ''),
            'rating' => $place['rating'] ?? 0,
            'location' => [
                'lat' => $location['latitude'] ?? 0,
                'lng' => $location['longitude'] ?? 0,
            ],
            'reviews' => $reviews,
        ];
    }

    protected function httpClient()
    {
        return Http::withOptions([
            'verify' => $this->resolveVerifyOption(),
        ]);
    }

    protected function placesNewClient()
    {
        // Places API (New) uses API key header instead of query param.
        return $this->httpClient()->withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
        ]);
    }

    protected function resolveVerifyOption()
    {
        $curlCaInfo = ini_get('curl.cainfo');
        $opensslCaFile = ini_get('openssl.cafile');

        if ($this->isReadableFile($curlCaInfo)) {
            return $curlCaInfo;
        }

        if ($this->isReadableFile($opensslCaFile)) {
            return $opensslCaFile;
        }

        // Fallback for local environments with broken CA path configuration.
        \Log::warning('GooglePlacesService: CA file not found, disabling SSL verify for this request.', [
            'curl.cainfo' => $curlCaInfo,
            'openssl.cafile' => $opensslCaFile,
        ]);

        return false;
    }

    protected function isReadableFile($path)
    {
        return is_string($path) && $path !== '' && file_exists($path) && is_readable($path);
    }
} 