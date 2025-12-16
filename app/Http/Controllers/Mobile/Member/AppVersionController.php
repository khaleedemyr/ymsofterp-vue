<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AppVersionController extends Controller
{
    /**
     * Check app version and return update information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkVersion(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'current_version' => 'required|string',
                'platform' => 'required|string|in:android,ios',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $currentVersion = $request->input('current_version');
            $platform = $request->input('platform'); // 'android' or 'ios'

            // Get latest version from database or config
            // Option 1: From database table (if exists)
            $latestVersion = $this->getLatestVersionFromDB($platform);
            
            // Option 2: From config file (fallback)
            if (!$latestVersion) {
                $latestVersion = $this->getLatestVersionFromConfig($platform);
            }

            if (!$latestVersion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Version information not found'
                ], 404);
            }

            // Compare versions
            $needsUpdate = $this->compareVersions($currentVersion, $latestVersion['version']);

            // Get store URLs
            $storeUrl = $platform === 'android' 
                ? ($latestVersion['play_store_url'] ?? 'https://play.google.com/store/apps/details?id=com.justusgroup.memberapp')
                : ($latestVersion['app_store_url'] ?? 'https://apps.apple.com/app/id123456789');

            $response = [
                'success' => true,
                'needs_update' => $needsUpdate,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion['version'],
                'platform' => $platform,
                'store_url' => $storeUrl,
                'force_update' => $latestVersion['force_update'] ?? false,
                'update_message' => $latestVersion['update_message'] ?? 'A new version of the app is available. Please update to continue.',
                'whats_new' => $latestVersion['whats_new'] ?? null,
            ];

            Log::info('App version check', [
                'platform' => $platform,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion['version'],
                'needs_update' => $needsUpdate,
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('App version check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check app version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest version from database
     */
    private function getLatestVersionFromDB($platform)
    {
        try {
            // Check if table exists
            $tableExists = DB::select("SHOW TABLES LIKE 'app_versions'");
            
            if (empty($tableExists)) {
                return null;
            }

            $version = DB::table('app_versions')
                ->where('platform', $platform)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($version) {
                return [
                    'version' => $version->version,
                    'play_store_url' => $version->play_store_url ?? null,
                    'app_store_url' => $version->app_store_url ?? null,
                    'force_update' => (bool)($version->force_update ?? false),
                    'update_message' => $version->update_message ?? null,
                    'whats_new' => $version->whats_new ?? null,
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to get version from database', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get latest version from config file
     */
    private function getLatestVersionFromConfig($platform)
    {
        $configKey = $platform === 'android' ? 'app.android_latest_version' : 'app.ios_latest_version';
        $version = config($configKey);

        if ($version) {
            return [
                'version' => $version,
                'play_store_url' => config('app.android_play_store_url'),
                'app_store_url' => config('app.ios_app_store_url'),
                'force_update' => config('app.force_update', false),
                'update_message' => config('app.update_message'),
                'whats_new' => config('app.whats_new'),
            ];
        }

        return null;
    }

    /**
     * Compare two version strings
     * Returns true if current version is older than latest version
     */
    private function compareVersions($currentVersion, $latestVersion)
    {
        // Remove 'v' prefix if exists
        $current = str_replace('v', '', strtolower($currentVersion));
        $latest = str_replace('v', '', strtolower($latestVersion));

        // Compare version strings
        return version_compare($current, $latest, '<');
    }
}

