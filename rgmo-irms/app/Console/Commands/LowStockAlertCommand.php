<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class LowStockAlertCommand extends Command
{
    protected $signature = 'app:low-stock-alert';
    protected $description = 'Generate low-stock notifications for items below reorder threshold.';

    public function handle(NotificationService $notificationService): int
    {
        $items = InventoryItem::lowStock()->active()->get();
        $sent = 0;

        foreach ($items as $item) {
            $message = "Item '{$item->name}' is low in stock ({$item->stock}).";

            if (Notification::where('type', 'low_stock')->where('message', $message)->doesntExist()) {
                $notificationService->notifyLowStock($item->name, $item->stock);
                $sent++;
            }
        }

        $this->info("{$sent} low stock notification(s) generated.");

        return self::SUCCESS;
    }
}
