<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AICacheService
{
    /**
     * Get cached query result
     * 
     * @param string $queryHash Hash of query + context + params
     * @return array|null
     */
    public function getCachedQuery($queryHash)
    {
        $cached = DB::table('ai_query_cache')
            ->where('query_hash', $queryHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            // Update hit count
            DB::table('ai_query_cache')
                ->where('id', $cached->id)
                ->increment('hit_count');

            return [
                'response' => $cached->response,
                'tokens_used' => $cached->tokens_used,
                'cost_rupiah' => $cached->cost_rupiah,
                'model_used' => $cached->model_used,
                'hit_count' => $cached->hit_count + 1
            ];
        }

        return null;
    }

    /**
     * Cache query result
     * 
     * @param string $queryHash Hash of query + context + params
     * @param string $queryText Original query text
     * @param string $contextType Context type (sales, inventory, cross, bom)
     * @param array|null $contextData Additional context data
     * @param string $response AI response
     * @param int $tokensUsed Total tokens used
     * @param float $costRupiah Cost in Rupiah
     * @param string $modelUsed Model used
     * @param int $ttlMinutes Cache TTL in minutes (default 30)
     * @return void
     */
    public function cacheQuery(
        $queryHash,
        $queryText,
        $contextType,
        $contextData,
        $response,
        $tokensUsed,
        $costRupiah,
        $modelUsed,
        $ttlMinutes = 30
    ) {
        $expiresAt = now()->addMinutes($ttlMinutes);

        DB::table('ai_query_cache')->updateOrInsert(
            ['query_hash' => $queryHash],
            [
                'query_text' => $queryText,
                'context_type' => $contextType,
                'context_data' => $contextData ? json_encode($contextData) : null,
                'response' => $response,
                'tokens_used' => $tokensUsed,
                'cost_rupiah' => $costRupiah,
                'model_used' => $modelUsed,
                'expires_at' => $expiresAt,
                'hit_count' => 0,
                'updated_at' => now()
            ]
        );
    }

    /**
     * Get similar queries (for semantic caching)
     * 
     * @param string $query Original query
     * @param float $threshold Similarity threshold (0-1)
     * @return array
     */
    public function getSimilarQueries($query, $threshold = 0.8)
    {
        // Simple implementation: check if query text contains similar keywords
        // For production, consider using embedding-based similarity
        $keywords = $this->extractKeywords($query);
        
        if (empty($keywords)) {
            return [];
        }

        $cached = DB::table('ai_query_cache')
            ->where('expires_at', '>', now())
            ->get();

        $similar = [];
        foreach ($cached as $cache) {
            $cacheKeywords = $this->extractKeywords($cache->query_text ?? '');
            $similarity = $this->calculateSimilarity($keywords, $cacheKeywords);
            
            if ($similarity >= $threshold) {
                $similar[] = [
                    'query_hash' => $cache->query_hash,
                    'response' => $cache->response,
                    'similarity' => $similarity,
                    'tokens_used' => $cache->tokens_used,
                    'cost_rupiah' => $cache->cost_rupiah,
                    'model_used' => $cache->model_used
                ];
            }
        }

        // Sort by similarity descending
        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return $similar;
    }

    /**
     * Invalidate cache by pattern
     * 
     * @param string $pattern Pattern to match (context_type, model_used, etc)
     * @param mixed $value Value to match
     * @return int Number of records deleted
     */
    public function invalidateCache($pattern, $value)
    {
        return DB::table('ai_query_cache')
            ->where($pattern, $value)
            ->delete();
    }

    /**
     * Clean expired cache
     * 
     * @return int Number of records deleted
     */
    public function cleanExpiredCache()
    {
        return DB::table('ai_query_cache')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getCacheStatistics()
    {
        $stats = DB::table('ai_query_cache')
            ->selectRaw('
                COUNT(*) as total_queries,
                SUM(CASE WHEN hit_count > 0 THEN 1 ELSE 0 END) as cached_queries,
                SUM(hit_count) as total_hits,
                SUM(cost_rupiah * hit_count) as total_cost_saved,
                AVG(hit_count) as avg_hits_per_query
            ')
            ->first();

        $hitRate = $stats->total_queries > 0 
            ? ($stats->cached_queries / $stats->total_queries) * 100 
            : 0;

        return [
            'total_queries' => $stats->total_queries ?? 0,
            'cached_queries' => $stats->cached_queries ?? 0,
            'total_hits' => $stats->total_hits ?? 0,
            'total_cost_saved' => $stats->total_cost_saved ?? 0,
            'avg_hits_per_query' => round($stats->avg_hits_per_query ?? 0, 2),
            'hit_rate_percent' => round($hitRate, 2)
        ];
    }

    /**
     * Extract keywords from query text
     * 
     * @param string $text Query text
     * @return array
     */
    private function extractKeywords($text)
    {
        // Simple keyword extraction (remove common words)
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can'];
        
        $words = str_word_count(strtolower($text), 1);
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        return array_unique(array_values($keywords));
    }

    /**
     * Calculate similarity between two keyword arrays
     * 
     * @param array $keywords1 First keyword array
     * @param array $keywords2 Second keyword array
     * @return float Similarity score (0-1)
     */
    private function calculateSimilarity($keywords1, $keywords2)
    {
        if (empty($keywords1) || empty($keywords2)) {
            return 0;
        }

        $intersection = array_intersect($keywords1, $keywords2);
        $union = array_unique(array_merge($keywords1, $keywords2));

        // Jaccard similarity
        return count($intersection) / max(count($union), 1);
    }

    /**
     * Generate query hash
     * 
     * @param string $query Query text
     * @param string $contextType Context type
     * @param array|null $params Additional parameters
     * @return string
     */
    public function generateQueryHash($query, $contextType, $params = null)
    {
        $data = [
            'query' => $query,
            'context_type' => $contextType,
            'params' => $params
        ];

        return md5(json_encode($data));
    }
}

