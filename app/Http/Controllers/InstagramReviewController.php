<?php

namespace App\Http\Controllers;

use App\Jobs\SyncInstagramCommentsJob;
use App\Jobs\SyncInstagramPostsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstagramReviewController extends Controller
{
    public function syncPosts(Request $request)
    {
        $validKeys = array_keys(config('instagram.profiles', []));
        $request->validate([
            'profile_keys' => 'nullable|array',
            'profile_keys.*' => ['string', Rule::in($validKeys)],
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $selected = $request->input('profile_keys');
        $keys = is_array($selected) ? array_values(array_filter($selected)) : [];
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $operationId = (string) Str::uuid();
        $this->putProgress($operationId, 'queued', 'Menunggu worker memproses sinkron posting...', 1, 0);

        $ranSync = (bool) config('instagram.dispatch_sync', false);
        if ($ranSync) {
            try {
                Bus::dispatchSync(new SyncInstagramPostsJob($keys, $operationId, $dateFrom, $dateTo));
            } catch (\Throwable $e) {
                $payload = [
                    'success' => false,
                    'ran_sync' => true,
                    'operation_id' => $operationId,
                    'error' => $e->getMessage(),
                ];
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json($payload, 422);
                }
                return redirect()->back()->with('instagram_flash', $payload);
            }
            $payload = [
                'success' => true,
                'ran_sync' => true,
                'operation_id' => $operationId,
                'message' => 'Sinkron posting selesai (mode langsung). Data sudah disimpan; tabel di bawah bisa dimuat ulang.',
            ];
        } else {
            SyncInstagramPostsJob::dispatch($keys, $operationId, $dateFrom, $dateTo);
            $payload = [
                'success' => true,
                'ran_sync' => false,
                'operation_id' => $operationId,
                'message' => 'Sinkronisasi posting dimasukkan ke antrian "'.config('instagram.process_queue', 'instagram-scraper').'".',
            ];
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }
        return redirect()->back()->with('instagram_flash', $payload);
    }

    public function syncComments(Request $request)
    {
        $validKeys = array_keys(config('instagram.profiles', []));
        $request->validate([
            'profile_keys' => 'nullable|array',
            'profile_keys.*' => ['string', Rule::in($validKeys)],
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $selected = $request->input('profile_keys');
        $keys = is_array($selected) ? array_values(array_filter($selected)) : [];
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $operationId = (string) Str::uuid();
        $this->putProgress($operationId, 'queued', 'Menunggu worker memproses sinkron komentar...', 1, 0);

        $ranSync = (bool) config('instagram.dispatch_sync', false);
        if ($ranSync) {
            try {
                Bus::dispatchSync(new SyncInstagramCommentsJob($keys, $operationId, $dateFrom, $dateTo));
            } catch (\Throwable $e) {
                $payload = [
                    'success' => false,
                    'ran_sync' => true,
                    'operation_id' => $operationId,
                    'error' => $e->getMessage(),
                ];
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json($payload, 422);
                }
                return redirect()->back()->with('instagram_flash', $payload);
            }
            $payload = [
                'success' => true,
                'ran_sync' => true,
                'operation_id' => $operationId,
                'message' => 'Sinkron komentar selesai (mode langsung).',
            ];
        } else {
            SyncInstagramCommentsJob::dispatch($keys, $operationId, $dateFrom, $dateTo);
            $payload = [
                'success' => true,
                'ran_sync' => false,
                'operation_id' => $operationId,
                'message' => 'Sinkron komentar dimasukkan ke antrian (banyak batch).',
            ];
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }
        return redirect()->back()->with('instagram_flash', $payload);
    }

    public function progress(Request $request)
    {
        $request->validate([
            'operation_id' => 'required|string|max:100',
        ]);

        $payload = Cache::get('instagram:sync:'.$request->query('operation_id'));
        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'Progress tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }
        return response()->json(array_merge(['success' => true], $payload));
    }

    public function stats()
    {
        try {
            return response()->json([
                'success' => true,
                'posts' => (int) DB::table('instagram_posts')->count(),
                'comments' => (int) DB::table('instagram_comments')->count(),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => true,
                'posts' => 0,
                'comments' => 0,
            ]);
        }
    }

    public function recentPosts(Request $request)
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        try {
            $posts = DB::table('instagram_posts')
                ->orderByDesc('post_timestamp')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(function ($r) {
                    $raw = null;
                    if (! empty($r->raw_json)) {
                        $decoded = json_decode((string) $r->raw_json, true);
                        if (is_array($decoded)) {
                            $raw = $decoded;
                        }
                    }

                    $likesCount = isset($r->likes_count) ? (int) $r->likes_count : (int) ($raw['likesCount'] ?? 0);
                    $viewsCount = isset($r->views_count) ? (int) $r->views_count : (int) ($raw['videoViewCount'] ?? ($raw['videoPlayCount'] ?? ($raw['video_view_count'] ?? 0)));
                    $mediaUrl = (string) ($r->media_url ?? '');
                    if ($mediaUrl === '') {
                        $mediaUrl = (string) ($raw['displayUrl'] ?? '');
                    }
                    if ($mediaUrl === '' && isset($raw['images'][0])) {
                        $mediaUrl = (string) $raw['images'][0];
                    }

                    return [
                        'id' => $r->id,
                        'profile_key' => $r->profile_key,
                        'short_code' => $r->short_code,
                        'post_url' => $r->post_url,
                        'comments_count' => (int) $r->comments_count,
                        'owner_username' => $r->owner_username,
                        'caption' => $r->caption,
                        'likes_count' => $likesCount,
                        'views_count' => $viewsCount,
                        'media_url' => $mediaUrl,
                        'post_timestamp' => $r->post_timestamp,
                    ];
                });
        } catch (\Throwable) {
            $posts = collect();
        }

        return response()->json([
            'success' => true,
            'posts' => $posts,
        ]);
    }

    protected function putProgress(string $operationId, string $status, string $message, int $total, int $done): void
    {
        Cache::put('instagram:sync:'.$operationId, [
            'status' => $status,
            'message' => $message,
            'progress_total' => max(1, $total),
            'progress_done' => max(0, min($done, max(1, $total))),
            'updated_at' => now()->toDateTimeString(),
        ], now()->addHours(6));
    }
}
