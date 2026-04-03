<?php

namespace App\Http\Controllers;

use App\Jobs\SyncInstagramCommentsJob;
use App\Jobs\SyncInstagramPostsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstagramReviewController extends Controller
{
    public function syncPosts(Request $request)
    {
        $validKeys = array_keys(config('instagram.profiles', []));
        $request->validate([
            'profile_keys' => 'nullable|array',
            'profile_keys.*' => ['string', Rule::in($validKeys)],
        ]);

        $selected = $request->input('profile_keys');
        $keys = is_array($selected) ? array_values(array_filter($selected)) : [];

        SyncInstagramPostsJob::dispatch($keys);

        $payload = [
            'success' => true,
            'message' => 'Sinkronisasi posting Instagram dimasukkan ke antrian. Pastikan worker memproses antrian "'.config('instagram.process_queue', 'instagram-scraper').'".',
        ];

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
        ]);

        $selected = $request->input('profile_keys');
        $keys = is_array($selected) ? array_values(array_filter($selected)) : [];

        SyncInstagramCommentsJob::dispatch($keys);

        $payload = [
            'success' => true,
            'message' => 'Sinkronisasi komentar Instagram dimasukkan ke antrian (beberapa batch). Worker antrian "'.config('instagram.process_queue', 'instagram-scraper').'" harus jalan.',
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->back()->with('instagram_flash', $payload);
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
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'profile_key' => $r->profile_key,
                    'short_code' => $r->short_code,
                    'post_url' => $r->post_url,
                    'comments_count' => (int) $r->comments_count,
                    'owner_username' => $r->owner_username,
                    'post_timestamp' => $r->post_timestamp,
                ]);
        } catch (\Throwable) {
            $posts = collect();
        }

        return response()->json([
            'success' => true,
            'posts' => $posts,
        ]);
    }
}
