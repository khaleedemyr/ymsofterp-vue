<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ActiveUsersMonitorController extends Controller
{
    /**
     * Display the monitoring dashboard
     */
    public function index()
    {
        return Inertia::render('Monitoring/ActiveUsers');
    }

    /**
     * API: Get active users statistics
     */
    public function getStats(Request $request)
    {
        $timeWindow = $request->input('time_window', 30); // minutes, default 30 minutes
        
        // Get current active sessions (last activity within time window)
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>', now()->subMinutes($timeWindow)->timestamp)
            ->get();

        // Count total active sessions
        $totalActive = $activeSessions->count();

        // Get unique users - use user_id column directly from sessions table
        $uniqueUsers = DB::table('sessions')
            ->where('last_activity', '>', now()->subMinutes($timeWindow)->timestamp)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        // Get active sessions with user info
        $activeSessionsWithUsers = [];
        foreach ($activeSessions as $session) {
            if ($session->user_id) {
                $user = DB::table('users')
                    ->where('id', $session->user_id)
                    ->select('id', 'nama_lengkap', 'email', 'avatar')
                    ->first();
                
                if ($user) {
                    $activeSessionsWithUsers[] = [
                        'session_id' => $session->id,
                        'user_id' => $session->user_id,
                        'user_name' => $user->nama_lengkap,
                        'user_email' => $user->email,
                        'user_avatar' => $user->avatar,
                        'last_activity' => Carbon::createFromTimestamp($session->last_activity)->format('Y-m-d H:i:s'),
                        'last_activity_timestamp' => $session->last_activity,
                        'ip_address' => $session->ip_address ?? '-',
                        'user_agent' => $this->parseUserAgent($session->user_agent ?? ''),
                    ];
                }
            }
        }

        // Get historical data for chart (last 1 hour, grouped by 5 minutes)
        $chartData = $this->getHistoricalData($timeWindow);

        // Get peak time today
        $peakTime = $this->getPeakTime();

        return response()->json([
            'success' => true,
            'data' => [
                'total_active_sessions' => $totalActive,
                'unique_active_users' => $uniqueUsers,
                'time_window_minutes' => $timeWindow,
                'active_sessions' => $activeSessionsWithUsers,
                'chart_data' => $chartData,
                'peak_time' => $peakTime,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Get historical data for chart
     */
    private function getHistoricalData($timeWindow = 30)
    {
        $data = [];
        $startTime = now()->subMinutes($timeWindow);
        
        // Group by 5-minute intervals
        for ($i = $timeWindow; $i >= 0; $i -= 5) {
            $intervalStart = now()->subMinutes($i);
            $intervalEnd = now()->subMinutes(max(0, $i - 5));
            
            $count = DB::table('sessions')
                ->where('last_activity', '>', $intervalEnd->timestamp)
                ->where('last_activity', '<=', $intervalStart->timestamp)
                ->count();
            
            $data[] = [
                'time' => $intervalStart->format('H:i'),
                'timestamp' => $intervalStart->timestamp,
                'count' => $count,
            ];
        }
        
        return $data;
    }

    /**
     * Get peak time today
     */
    private function getPeakTime()
    {
        $todayStart = Carbon::today()->timestamp;
        
        // Get all sessions today and group by hour manually
        $sessions = DB::table('sessions')
            ->where('last_activity', '>=', $todayStart)
            ->select('last_activity')
            ->get();
        
        $hourlyCount = [];
        foreach ($sessions as $session) {
            $hour = (int) date('H', $session->last_activity);
            if (!isset($hourlyCount[$hour])) {
                $hourlyCount[$hour] = 0;
            }
            $hourlyCount[$hour]++;
        }
        
        if (empty($hourlyCount)) {
            return null;
        }
        
        arsort($hourlyCount);
        $peakHour = array_key_first($hourlyCount);
        $peakCount = $hourlyCount[$peakHour];
        
        return [
            'hour' => $peakHour,
            'count' => $peakCount,
            'formatted' => sprintf('%02d:00', $peakHour),
        ];
    }

    /**
     * Parse user agent to get browser/device info
     */
    private function parseUserAgent($userAgent)
    {
        if (empty($userAgent)) {
            return 'Unknown';
        }

        // Simple browser detection
        if (stripos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (stripos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) {
            return 'Safari';
        } elseif (stripos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (stripos($userAgent, 'Opera') !== false) {
            return 'Opera';
        }

        return substr($userAgent, 0, 50);
    }
}

