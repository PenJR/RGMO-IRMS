<?php

namespace App\Console\Commands;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class LowStockAlertCommand extends Command
{
    protected $signature = 'app:low-stock-alert';

    protected $description = 'Monitor critical stock and batches with expired, near-expiry, or missing expiry dates.';

    /**
     * Execute the command or middleware action.
     */
    public function handle(NotificationService $notificationService): int
    {
        $criticalCount = 0;
        InventoryItem::active()->lowStock()->withReservationBalance()->chunkById(200,
            function ($items) use ($notificationService, &$criticalCount) {
                foreach ($items as $item) {
                    $notificationService->notifyLowStock($item->name, $item->usable_stock, $item->id);
                    $criticalCount++;
                }
            });

        $batchCount = 0;
        InventoryBatch::query()->with('inventoryItem')->where('stock', '>', 0)
            ->whereHas('inventoryItem', fn ($items) => $items->whereNull('deleted_at'))
            ->where(fn ($dates) => $dates
                ->whereDate('expiry_date', '<=', today()->addDays((int) config('inventory.expiry_alert_days', 30)))
                ->orWhere(fn ($missing) => $missing->whereNull('expiry_date')
                    ->whereHas('inventoryItem', fn ($items) => $items->where('has_expiry', true))))
            ->chunkById(200, function ($batches) use ($notificationService, &$batchCount) {
                foreach ($batches as $batch) {
                    $notificationService->notifyBatchExpiry($batch);
                    $batchCount++;
                }
            });

        $this->info("Monitoring complete: {$criticalCount} critical stock item(s) and {$batchCount} expiry risk batch(es) checked.");

        return self::SUCCESS;
    }
}
