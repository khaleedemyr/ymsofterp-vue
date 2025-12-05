<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OptimizePackingDOMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Rate limiting untuk operasi yang berat
        if ($this->isHeavyOperation($request)) {
            $userId = auth()->id();
            $operationKey = $this->getOperationKey($request, $userId);
            
            // Cek apakah user sedang melakukan operasi yang sama
            if (Cache::has($operationKey)) {
                Log::warning('User attempting duplicate heavy operation', [
                    'user_id' => $userId,
                    'operation' => $operationKey,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'error' => 'Operasi sedang berjalan, silakan tunggu sebentar',
                    'message' => 'Mohon tunggu operasi sebelumnya selesai'
                ], 429);
            }
            
            // Set lock untuk operasi ini (5 menit)
            Cache::put($operationKey, true, 300);
            
            // Cleanup lock setelah response
            $response = $next($request);
            
            // Hapus lock setelah operasi selesai
            register_shutdown_function(function() use ($operationKey) {
                Cache::forget($operationKey);
            });
            
            return $response;
        }
        
        return $next($request);
    }
    
    /**
     * Cek apakah request ini adalah operasi yang berat
     */
    private function isHeavyOperation(Request $request): bool
    {
        $heavyOperations = [
            'packing-list.store',
            'delivery-order.store',
            'packing-list.summary',
            'packing-list.matrix',
            'packing-list.export-summary',
            'packing-list.export-matrix',
            'delivery-order.export-summary',
            'delivery-order.export-detail',
        ];
        
        $routeName = $request->route()?->getName();
        return in_array($routeName, $heavyOperations);
    }
    
    /**
     * Generate key untuk operasi
     */
    private function getOperationKey(Request $request, int $userId): string
    {
        $routeName = $request->route()?->getName();
        $params = $request->only(['food_floor_order_id', 'packing_list_id', 'tanggal']);
        
        return "heavy_operation:{$userId}:{$routeName}:" . md5(serialize($params));
    }
}
