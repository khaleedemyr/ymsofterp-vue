<?php

namespace App\Http\Controllers;

use App\Services\GooglePlacesService;
use App\Services\ApifyGoogleReviewsService;
use Illuminate\Http\Request;

class GoogleReviewController extends Controller
{
    protected $placesService;
    protected $apifyService;

    public function __construct(GooglePlacesService $placesService, ApifyGoogleReviewsService $apifyService)
    {
        $this->placesService = $placesService;
        $this->apifyService = $apifyService;
    }

    public function index()
    {
        $outlets = \DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet', 'place_id')
            ->whereNotNull('place_id')
            ->get();

        return inertia('google-review/Index', [
            'outlets' => $outlets
        ]);
    }

    public function scrapeReviews(Request $request)
    {
        $request->validate([
            'place_id' => 'required|string'
        ]);

        \Log::info('GoogleReviewController@scrapeReviews', [
            'place_id' => $request->input('place_id'),
            'is_inertia' => $request->hasHeader('X-Inertia'),
        ]);

        try {
            $placeId = $request->input('place_id');
            \Log::info('Place ID:', [$placeId]);
            $placeDetails = $this->placesService->getPlaceDetails($placeId);
            \Log::info('Place Details:', [$placeDetails]);

            if ($request->hasHeader('X-Inertia')) {
                \Log::info('Return inertia redirect with result', [
                    'result' => [
                        'success' => true,
                        'place' => [
                            'name' => $placeDetails['name'],
                            'address' => $placeDetails['address'],
                            'rating' => $placeDetails['rating'],
                            'location' => $placeDetails['location']
                        ],
                        'reviews' => $placeDetails['reviews']
                    ]
                ]);
                return redirect()->back()->with('result', [
                    'success' => true,
                    'place' => [
                        'name' => $placeDetails['name'],
                        'address' => $placeDetails['address'],
                        'rating' => $placeDetails['rating'],
                        'location' => $placeDetails['location']
                    ],
                    'reviews' => $placeDetails['reviews']
                ]);
            }

            \Log::info('Return JSON result', [
                'success' => true,
                'place' => [
                    'name' => $placeDetails['name'],
                    'address' => $placeDetails['address'],
                    'rating' => $placeDetails['rating'],
                    'location' => $placeDetails['location']
                ],
                'reviews' => $placeDetails['reviews']
            ]);
            return response()->json([
                'success' => true,
                'place' => [
                    'name' => $placeDetails['name'],
                    'address' => $placeDetails['address'],
                    'rating' => $placeDetails['rating'],
                    'location' => $placeDetails['location']
                ],
                'reviews' => $placeDetails['reviews']
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching place details: ' . $e->getMessage());

            if ($request->hasHeader('X-Inertia')) {
                \Log::info('Return inertia redirect with error', [
                    'result' => [
                        'success' => false,
                        'error' => $e->getMessage()
                    ]
                ]);
                return redirect()->back()->with('result', [
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }

            \Log::info('Return JSON error', [
                'success' => false,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getScrapedReviews(Request $request)
    {
        $jsonPath = base_path('reviews.json');
        if (!file_exists($jsonPath)) {
            return response()->json([
                'success' => false,
                'error' => 'File reviews.json tidak ditemukan',
                'reviews' => []
            ], 404);
        }
        $json = file_get_contents($jsonPath);
        $reviews = json_decode($json, true);
        return response()->json([
            'success' => true,
            'reviews' => $reviews,
        ]);
    }

    public function scrapeReviewsApify(Request $request)
    {
        $request->validate([
            'place_id' => 'required|string',
            'max_reviews' => 'nullable|integer|min:1|max:2000',
        ]);

        \Log::info('GoogleReviewController@scrapeReviewsApify', [
            'place_id' => $request->input('place_id'),
            'max_reviews' => $request->input('max_reviews'),
            'is_inertia' => $request->hasHeader('X-Inertia'),
        ]);

        try {
            $placeId = $request->input('place_id');
            $maxReviews = (int)($request->input('max_reviews') ?? 200);

            $result = $this->apifyService->fetchReviewsByPlaceId($placeId, $maxReviews, 'newest');

            $payload = [
                'success' => true,
                'place' => $result['place'],
                'reviews' => $result['reviews'],
            ];

            if ($request->hasHeader('X-Inertia')) {
                return redirect()->back()->with('result', $payload);
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            \Log::error('Error fetching Apify reviews: ' . $e->getMessage());

            $payload = [
                'success' => false,
                'error' => $e->getMessage(),
            ];

            if ($request->hasHeader('X-Inertia')) {
                return redirect()->back()->with('result', $payload);
            }

            return response()->json($payload, 500);
        }
    }
} 