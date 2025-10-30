<?php

namespace App\Jobs;

use App\Http\Controllers\StockCutController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class RunStockCutForOutlet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $outletId;
    public string $tanggal;
    public ?string $typeFilter;

    public function __construct(int $outletId, string $tanggal, ?string $typeFilter = null)
    {
        $this->outletId = $outletId;
        $this->tanggal = $tanggal;
        $this->typeFilter = $typeFilter;
        $this->onQueue('stock-cut');
    }

    public function handle(): void
    {
        $key = sprintf('stockcut:%s:%s:%s', $this->outletId, $this->tanggal, $this->typeFilter ?: '-');
        $statusKey = 'stockcut_status:' . $key;
        $lock = Cache::lock('lock:' . $key, 60 * 30); // 30 minutes TTL

        if (!$lock->get()) {
            Cache::put($statusKey, ['status' => 'skipped_locked', 'message' => 'Outlet is being processed'], 60 * 30);
            return;
        }

        Cache::put($statusKey, ['status' => 'running', 'message' => 'Processing'], 60 * 30);

        try {
            $controller = app(StockCutController::class);
            $request = new Request([
                'tanggal' => $this->tanggal,
                'id_outlet' => $this->outletId,
                'type' => $this->typeFilter,
            ]);
            $response = $controller->potongStockOrderItems($request);
            $payload = method_exists($response, 'getData') ? (array) $response->getData(true) : ['status' => 'success'];
            Cache::put($statusKey, ['status' => $payload['status'] ?? 'success', 'message' => $payload['message'] ?? null], 60 * 30);
        } catch (\Throwable $e) {
            Cache::put($statusKey, ['status' => 'failed', 'message' => $e->getMessage()], 60 * 30);
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }
}


