<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    /**
     * Show monitoring dashboard
     */
    public function index()
    {
        return Inertia::render('Monitoring/Dashboard');
    }

    /**
     * Get MySQL processes
     */
    public function getMySQLProcesses()
    {
        try {
            $processes = DB::select("
                SELECT 
                    id,
                    user,
                    host,
                    db,
                    command,
                    time,
                    state,
                    LEFT(info, 200) as query_preview
                FROM information_schema.processlist
                WHERE command != 'Sleep'
                ORDER BY time DESC
                LIMIT 20
            ");

            return response()->json([
                'success' => true,
                'data' => $processes,
                'count' => count($processes)
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting MySQL processes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MySQL status
     */
    public function getMySQLStatus()
    {
        try {
            $status = DB::select("
                SHOW STATUS WHERE Variable_name IN (
                    'Threads_connected',
                    'Threads_running',
                    'Slow_queries',
                    'Questions',
                    'Uptime',
                    'Max_used_connections',
                    'Connections'
                )
            ");

            $statusMap = [];
            foreach ($status as $s) {
                $statusMap[$s->Variable_name] = $s->Value;
            }

            return response()->json([
                'success' => true,
                'data' => $statusMap
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting MySQL status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent slow queries
     */
    public function getSlowQueries(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);

            // Check if slow_log table exists and has data
            $count = DB::select("SELECT COUNT(*) as count FROM mysql.slow_log");
            $totalSlowQueries = $count[0]->count ?? 0;

            if ($totalSlowQueries > 0) {
                $slowQueries = DB::select("
                    SELECT 
                        sql_text,
                        query_time,
                        lock_time,
                        rows_examined,
                        rows_sent,
                        created_at
                    FROM mysql.slow_log 
                    WHERE sql_text NOT LIKE '%slow_log%'
                      AND sql_text NOT LIKE '%EXPLAIN%'
                    ORDER BY created_at DESC
                    LIMIT ?
                ", [$limit]);

                return response()->json([
                    'success' => true,
                    'data' => $slowQueries,
                    'total' => $totalSlowQueries
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No slow queries found. Slow query log might be disabled or empty.'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error getting slow queries: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get slow queries summary
     */
    public function getSlowQueriesSummary()
    {
        try {
            $summary = DB::select("
                SELECT 
                    sql_text,
                    COUNT(*) as call_count,
                    AVG(query_time) as avg_query_time,
                    MAX(query_time) as max_query_time,
                    SUM(query_time) as total_query_time,
                    AVG(rows_examined) as avg_rows_examined,
                    MAX(rows_examined) as max_rows_examined
                FROM mysql.slow_log 
                WHERE sql_text NOT LIKE '%slow_log%'
                  AND sql_text NOT LIKE '%EXPLAIN%'
                GROUP BY sql_text
                ORDER BY call_count DESC, avg_query_time DESC
                LIMIT 20
            ");

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting slow queries summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get table access statistics
     */
    public function getTableAccessStats()
    {
        try {
            $stats = DB::select("
                SELECT 
                    CASE 
                        WHEN sql_text LIKE '%FROM `%' THEN 
                            SUBSTRING_INDEX(SUBSTRING_INDEX(sql_text, 'FROM `', -1), '`', 1)
                        WHEN sql_text LIKE '%FROM %' THEN 
                            SUBSTRING_INDEX(SUBSTRING_INDEX(sql_text, 'FROM ', -1), ' ', 1)
                        ELSE 'unknown'
                    END as table_name,
                    COUNT(*) as access_count,
                    AVG(query_time) as avg_query_time,
                    MAX(query_time) as max_query_time,
                    SUM(rows_examined) as total_rows_examined
                FROM mysql.slow_log 
                WHERE sql_text NOT LIKE '%slow_log%'
                  AND sql_text NOT LIKE '%EXPLAIN%'
                  AND (sql_text LIKE '%FROM `%' OR sql_text LIKE '%FROM %')
                GROUP BY table_name
                ORDER BY access_count DESC
                LIMIT 20
            ");

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting table access stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get server performance metrics
     */
    public function getServerMetrics()
    {
        try {
            // Get PHP memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');

            // Get PHP-FPM processes count (if available)
            $phpProcesses = 0;
            if (function_exists('exec')) {
                $output = [];
                exec('ps aux | grep php-fpm | grep -v grep | wc -l', $output);
                $phpProcesses = (int) ($output[0] ?? 0);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'php' => [
                        'memory_usage' => $memoryUsage,
                        'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                        'memory_peak' => $memoryPeak,
                        'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                        'memory_limit' => $memoryLimit,
                        'processes' => $phpProcesses
                    ],
                    'timestamp' => now()->toDateTimeString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting server metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kill MySQL process
     */
    public function killProcess(Request $request)
    {
        $request->validate([
            'process_id' => 'required|integer'
        ]);

        try {
            DB::statement("KILL ?", [$request->process_id]);

            return response()->json([
                'success' => true,
                'message' => 'Process killed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error killing process: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active users from all applications
     */
    public function getActiveUsers()
    {
        try {
            $activeUsers = [];
            $now = now();

            // 1. Get active web sessions (Laravel sessions)
            try {
                $webSessions = DB::table('sessions')
                    ->where('last_activity', '>', $now->copy()->subMinutes(15)->timestamp)
                    ->get();

                foreach ($webSessions as $session) {
                    try {
                        $payload = unserialize(base64_decode($session->payload));
                        $userId = null;
                        
                        // Try different ways to get user ID from session
                        // Laravel stores user ID in different formats
                        foreach ($payload as $key => $value) {
                            // Check for login_web_* pattern
                            if (strpos($key, 'login_web_') === 0) {
                                $userId = $value;
                                break;
                            }
                            // Check for login_* pattern
                            if (strpos($key, 'login_') === 0 && is_numeric($value)) {
                                $userId = $value;
                                break;
                            }
                        }

                        if ($userId) {
                            $user = DB::table('users')->where('id', $userId)->first();
                            
                            if ($user) {
                                // Try to get current route from session
                                $currentRoute = $payload['last_route'] ?? $payload['last_path'] ?? null;
                                $currentMethod = $payload['last_method'] ?? null;
                                
                                $activeUsers[] = [
                                    'user_id' => $user->id,
                                    'name' => $user->nama_lengkap ?? $user->name ?? 'Unknown',
                                    'email' => $user->email ?? null,
                                    'application' => 'Web (Vue Frontend)',
                                    'session_id' => $session->id,
                                    'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
                                    'ip_address' => $session->ip_address ?? null,
                                    'user_agent' => $session->user_agent ?? null,
                                    'type' => 'web_session',
                                    'current_route' => $currentRoute,
                                    'current_method' => $currentMethod
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip invalid session payload
                        continue;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error getting web sessions: ' . $e->getMessage());
            }

            // 2. Get active API tokens (Sanctum - for mobile apps)
            try {
                $apiTokens = DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->where(function($query) use ($now) {
                        $query->where('last_used_at', '>', $now->copy()->subMinutes(15))
                              ->orWhere(function($q) use ($now) {
                                  $q->whereNull('last_used_at')
                                    ->where('created_at', '>', $now->copy()->subMinutes(15));
                              });
                    })
                    ->get();

                foreach ($apiTokens as $token) {
                    $user = DB::table('users')->where('id', $token->tokenable_id)->first();
                    
                    if ($user) {
                        // Determine application type from token name
                        $appType = 'Unknown App';
                        if (strpos($token->name, 'mobile-app') !== false) {
                            $appType = 'Member App (Mobile)';
                        } elseif (strpos($token->name, 'ymsoftapp') !== false) {
                            $appType = 'YMSoft App (Mobile)';
                        } elseif (strpos($token->name, 'ymsoftpos') !== false) {
                            $appType = 'YMSoft POS (Desktop)';
                        } elseif (strpos($token->name, 'api') !== false) {
                            $appType = 'API Client';
                        }

                        $activeUsers[] = [
                            'user_id' => $user->id,
                            'name' => $user->nama_lengkap ?? $user->name ?? 'Unknown',
                            'email' => $user->email ?? null,
                            'application' => $appType,
                            'token_id' => $token->id,
                            'token_name' => $token->name,
                            'last_activity' => $token->last_used_at ?? $token->created_at,
                            'type' => 'api_token'
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error getting API tokens: ' . $e->getMessage());
            }

            // 3. Get active member app users (member_apps_members with tokens)
            try {
                $memberTokens = DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\MemberAppsMember')
                    ->where(function($query) use ($now) {
                        $query->where('last_used_at', '>', $now->copy()->subMinutes(15))
                              ->orWhere(function($q) use ($now) {
                                  $q->whereNull('last_used_at')
                                    ->where('created_at', '>', $now->copy()->subMinutes(15));
                              });
                    })
                    ->get();

                foreach ($memberTokens as $token) {
                    $member = DB::table('member_apps_members')->where('id', $token->tokenable_id)->first();
                    
                    if ($member) {
                        $activeUsers[] = [
                            'user_id' => $member->id,
                            'name' => $member->nama_lengkap ?? 'Unknown Member',
                            'email' => $member->email ?? null,
                            'member_id' => $member->member_id ?? null,
                            'application' => 'Member App (Mobile)',
                            'token_id' => $token->id,
                            'token_name' => $token->name,
                            'last_activity' => $token->last_used_at ?? $token->created_at,
                            'type' => 'member_token'
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error getting member tokens: ' . $e->getMessage());
            }

            // 4. Get recent activity logs (if available)
            try {
                $recentActivities = DB::table('activity_logs')
                    ->where('created_at', '>', $now->copy()->subMinutes(15))
                    ->select('user_id', 'description', 'created_at', 'properties', 'subject_type', 'subject_id', 'causer_type', 'causer_id')
                    ->orderBy('created_at', 'desc')
                    ->limit(200)
                    ->get();

                // Group activities by user
                $userActivities = [];
                foreach ($recentActivities as $activity) {
                    $userId = $activity->user_id ?? $activity->causer_id;
                    if ($userId) {
                        if (!isset($userActivities[$userId])) {
                            $userActivities[$userId] = [];
                        }
                        $properties = json_decode($activity->properties ?? '{}', true);
                        $userActivities[$userId][] = [
                            'description' => $activity->description,
                            'created_at' => $activity->created_at,
                            'properties' => $properties,
                            'route' => $properties['route'] ?? $properties['url'] ?? $properties['path'] ?? null,
                            'method' => $properties['method'] ?? null,
                            'action' => $properties['action'] ?? null,
                        ];
                    }
                }

                // Merge activity info to active users
                foreach ($activeUsers as &$user) {
                    if (isset($userActivities[$user['user_id']])) {
                        $user['recent_activities'] = array_slice($userActivities[$user['user_id']], 0, 5);
                        $latestActivity = $userActivities[$user['user_id']][0];
                        $user['last_action'] = $latestActivity['description'] ?? null;
                        $user['last_route'] = $latestActivity['route'] ?? $user['current_route'] ?? null;
                        $user['last_method'] = $latestActivity['method'] ?? $user['current_method'] ?? null;
                    } else {
                        // Use current route from session if no activity log
                        if (isset($user['current_route'])) {
                            $user['last_route'] = $user['current_route'];
                            $user['last_method'] = $user['current_method'] ?? null;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error getting activity logs: ' . $e->getMessage());
            }

            // 5. Get recent access logs from nginx/apache (if available via database)
            // Alternative: Track current page from session or add middleware to log current route
            try {
                // Try to get route information from recent requests
                // This would require a custom logging mechanism
                // For now, we'll use activity_logs which should have route info
            } catch (\Exception $e) {
                // Silent fail
            }

            // Remove duplicates (same user from multiple sources)
            $uniqueUsers = [];
            foreach ($activeUsers as $user) {
                $key = $user['user_id'] . '_' . $user['application'];
                if (!isset($uniqueUsers[$key])) {
                    $uniqueUsers[$key] = $user;
                } else {
                    // Merge activities if exists
                    if (isset($user['recent_activities'])) {
                        if (!isset($uniqueUsers[$key]['recent_activities'])) {
                            $uniqueUsers[$key]['recent_activities'] = [];
                        }
                        $uniqueUsers[$key]['recent_activities'] = array_merge(
                            $uniqueUsers[$key]['recent_activities'] ?? [],
                            $user['recent_activities']
                        );
                    }
                }
            }

            $activeUsers = array_values($uniqueUsers);

            // Sort by last activity
            usort($activeUsers, function($a, $b) {
                $timeA = strtotime($a['last_activity']);
                $timeB = strtotime($b['last_activity']);
                return $timeB - $timeA;
            });

            // Calculate breakdown by application
            $breakdown = [];
            foreach ($activeUsers as $user) {
                $app = $user['application'];
                if (!isset($breakdown[$app])) {
                    $breakdown[$app] = [
                        'application' => $app,
                        'count' => 0,
                        'users' => []
                    ];
                }
                $breakdown[$app]['count']++;
                $breakdown[$app]['users'][] = $user;
            }

            // Sort breakdown by count (descending)
            uasort($breakdown, function($a, $b) {
                return $b['count'] - $a['count'];
            });

            return response()->json([
                'success' => true,
                'data' => $activeUsers,
                'count' => count($activeUsers),
                'breakdown' => array_values($breakdown),
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting active users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
