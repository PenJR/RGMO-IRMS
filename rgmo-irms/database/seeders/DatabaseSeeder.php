<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\LoginHistory;
use App\Models\Notification;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            'admin' => User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin User',
                    'role' => 'admin',
                    'department' => 'RGMO',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            ),
            'staff' => User::firstOrCreate(
                ['email' => 'staff@example.com'],
                [
                    'name' => 'Staff User',
                    'role' => 'staff',
                    'department' => 'Nursery',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            ),
            'pm' => User::firstOrCreate(
                ['email' => 'manager@example.com'],
                [
                    'name' => 'Project Manager',
                    'role' => 'project_manager',
                    'department' => 'Operations',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            ),
            'head' => User::firstOrCreate(
                ['email' => 'head@example.com'],
                [
                    'name' => 'RGMO Head',
                    'role' => 'rgmo_head',
                    'department' => 'RGMO',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            ),
            'test' => User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'role' => 'staff',
                    'department' => 'General',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            ),
        ];

        SystemSetting::set('site_name', 'RGMO RMS Demo');
        SystemSetting::set('default_currency', ['code' => 'PHP', 'symbol' => '₱']);
        SystemSetting::set('low_stock_threshold', 20);

        $catalog = [
            'Rice Seeds' => [
                ['sku' => 'SEED-RC226', 'name' => 'G3 - RC 226', 'unit' => 'bag', 'price' => 1850, 'min_stock' => 25],
                ['sku' => 'SEED-RC222', 'name' => 'GVF 2A - A - RC 222', 'unit' => 'bag', 'price' => 1725, 'min_stock' => 20],
                ['sku' => 'SEED-NSIC222', 'name' => 'NSIC Rc 222', 'unit' => 'bag', 'price' => 1650, 'min_stock' => 18],
                ['sku' => 'SEED-RC440', 'name' => 'F3 - RC 440', 'unit' => 'bag', 'price' => 1920, 'min_stock' => 16],
            ],
            'Fertilizers' => [
                ['sku' => 'FERT-UREA', 'name' => 'Urea 46-0-0', 'unit' => 'bag', 'price' => 1450, 'min_stock' => 12],
                ['sku' => 'FERT-16-20', 'name' => 'Complete 16-20-0', 'unit' => 'bag', 'price' => 1680, 'min_stock' => 12],
                ['sku' => 'FERT-14-14', 'name' => 'NPK 14-14-14', 'unit' => 'bag', 'price' => 1740, 'min_stock' => 10],
            ],
            'Chemicals' => [
                ['sku' => 'CHEM-HERB', 'name' => 'Selective Herbicide', 'unit' => 'bottle', 'price' => 620, 'min_stock' => 8],
                ['sku' => 'CHEM-FUNG', 'name' => 'Systemic Fungicide', 'unit' => 'bottle', 'price' => 710, 'min_stock' => 8],
                ['sku' => 'CHEM-INSECT', 'name' => 'Insecticide EC', 'unit' => 'bottle', 'price' => 560, 'min_stock' => 6],
            ],
            'Office Supplies' => [
                ['sku' => 'OFF-A4', 'name' => 'A4 Bond Paper', 'unit' => 'ream', 'price' => 210, 'min_stock' => 10],
                ['sku' => 'OFF-BALLPEN', 'name' => 'Ballpen Blue', 'unit' => 'box', 'price' => 165, 'min_stock' => 8],
            ],
        ];

        $items = collect();

        foreach ($catalog as $categoryName => $products) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['description' => "Seeded category for {$categoryName}."]
            );

            foreach ($products as $product) {
                $item = InventoryItem::updateOrCreate(
                    ['sku' => $product['sku']],
                    [
                        'category_id' => $category->id,
                        'name' => $product['name'],
                        'unit' => $product['unit'],
                        'price' => $product['price'],
                        'min_stock' => $product['min_stock'],
                        'reorder_level' => max(1, $product['min_stock'] - 4),
                        'description' => "Seeded item {$product['name']} for visualization and reports.",
                        'planting_date' => $categoryName === 'Rice Seeds' ? now()->subMonths(3) : null,
                    ]
                );

                $items->push($item);
            }
        }

        foreach ($items as $index => $item) {
            if ($item->transactions()->exists()) {
                continue;
            }

            $runningStock = 0;
            for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
                $date = now()->subMonths($monthOffset)->setDay(6)->setHour(9);
                $stockIn = 28 + (($index + $monthOffset) % 18);
                $stockOut = 14 + (($index * 2 + $monthOffset) % 11);
                $fundingSource = ['RGMO', 'DA Grant', 'DA Hybrid'][($index + $monthOffset) % 3];

                DB::table('inventory_transactions')->insert([
                    'inventory_item_id' => $item->id,
                    'user_id' => $users['admin']->id,
                    'transaction_type' => 'stock_in',
                    'quantity' => $stockIn,
                    'funding_source' => $fundingSource,
                    'source' => 'Monthly replenishment',
                    'destination' => null,
                    'meta' => json_encode(['seeded' => true]),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                DB::table('inventory_transactions')->insert([
                    'inventory_item_id' => $item->id,
                    'user_id' => $users['pm']->id,
                    'transaction_type' => 'stock_out',
                    'quantity' => $stockOut,
                    'funding_source' => $fundingSource,
                    'source' => null,
                    'destination' => 'Project issuance',
                    'meta' => json_encode(['seeded' => true]),
                    'created_at' => $date->copy()->addDays(10),
                    'updated_at' => $date->copy()->addDays(10),
                ]);

                $runningStock += ($stockIn - $stockOut);
            }

            if ($index % 4 === 0) {
                $runningStock = max(2, (int) floor($item->min_stock * 0.6));
            }

            $item->update(['stock' => $runningStock]);
        }

        if (ResourceRequest::count() < 16) {
            for ($i = 1; $i <= 16; $i++) {
                $requestedAt = now()->subDays(48 - ($i * 2));
                $status = match ($i % 5) {
                    0 => ResourceRequest::STATUS_REJECTED,
                    1 => ResourceRequest::STATUS_PENDING,
                    2 => ResourceRequest::STATUS_APPROVED,
                    3 => ResourceRequest::STATUS_COMPLETED,
                    default => ResourceRequest::STATUS_CANCELLED,
                };

                $requestUser = $i % 2 === 0 ? $users['staff'] : $users['pm'];

                $request = ResourceRequest::create([
                    'user_id' => $requestUser->id,
                    'ris_no' => 'RIS-' . now()->format('Y') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'responsible_center' => $requestUser->department ?? 'RGMO',
                    'status' => $status,
                    'purpose' => 'Seeded request #' . $i . ' for dashboard and report visualizations.',
                    'remarks' => in_array($status, [ResourceRequest::STATUS_REJECTED, ResourceRequest::STATUS_CANCELLED], true) ? 'Seeded status remarks.' : null,
                    'requested_date' => $requestedAt,
                    'needed_date' => $requestedAt->copy()->addDays(5),
                    'approved_by' => in_array($status, [ResourceRequest::STATUS_APPROVED, ResourceRequest::STATUS_COMPLETED], true) ? $users['head']->id : null,
                    'approved_at' => in_array($status, [ResourceRequest::STATUS_APPROVED, ResourceRequest::STATUS_COMPLETED], true) ? $requestedAt->copy()->addDays(2) : null,
                    'rejected_at' => $status === ResourceRequest::STATUS_REJECTED ? $requestedAt->copy()->addDays(2) : null,
                    'cancelled_at' => $status === ResourceRequest::STATUS_CANCELLED ? $requestedAt->copy()->addDays(1) : null,
                ]);

                $request->updateQuietly([
                    'created_at' => $requestedAt,
                    'updated_at' => $requestedAt,
                ]);

                $lineItems = $items->shuffle()->take(2);
                foreach ($lineItems as $lineItem) {
                    RequestItem::create([
                        'resource_request_id' => $request->id,
                        'inventory_item_id' => $lineItem->id,
                        'quantity' => rand(2, 12),
                    ]);
                }

                if ($status === ResourceRequest::STATUS_COMPLETED) {
                    foreach ($lineItems as $lineItem) {
                        ResourceUsage::create([
                            'inventory_item_id' => $lineItem->id,
                            'user_id' => $requestUser->id,
                            'field_id' => 'FIELD-' . str_pad((string) rand(1, 8), 2, '0', STR_PAD_LEFT),
                            'quantity' => rand(1, 8),
                            'notes' => 'Seeded usage from completed request #' . $request->id,
                        ]);
                    }
                }
            }
        }

        if (AuditLog::count() < 30) {
            $modules = ['inventory', 'resource_request', 'reports', 'auth', 'notifications'];
            $actions = ['create', 'update', 'approve', 'reject', 'export', 'login'];

            for ($i = 0; $i < 30; $i++) {
                $createdAt = now()->subDays(30 - $i)->setHour(8 + ($i % 8));

                AuditLog::create([
                    'user_id' => $i % 4 === 0 ? $users['admin']->id : ($i % 2 === 0 ? $users['head']->id : $users['staff']->id),
                    'action' => $actions[$i % count($actions)],
                    'module' => $modules[$i % count($modules)],
                    'details' => ['seeded' => true, 'note' => 'Generated sample audit activity'],
                    'ip_address' => '127.0.0.1',
                    'model_type' => InventoryItem::class,
                    'model_id' => $items->random()->id,
                    'old_values' => null,
                    'new_values' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        if (LoginHistory::count() < 24) {
            foreach ($users as $user) {
                for ($i = 0; $i < 8; $i++) {
                    $loginAt = now()->subDays($i * 3 + 1)->setHour(8 + ($i % 4));
                    LoginHistory::create([
                        'user_id' => $user->id,
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Seeded Browser',
                        'login_at' => $loginAt,
                        'logout_at' => $loginAt->copy()->addHours(rand(1, 6)),
                    ]);
                }
            }
        }

        if (Notification::count() < 15) {
            foreach ([$users['staff'], $users['pm'], $users['head']] as $recipient) {
                for ($i = 1; $i <= 5; $i++) {
                    Notification::create([
                        'user_id' => $recipient->id,
                        'type' => $i % 2 === 0 ? 'request_status' : 'low_stock',
                        'message' => 'Seeded notification #' . $i . ' for ' . $recipient->name,
                        'read_at' => $i % 3 === 0 ? now()->subDays(1) : null,
                    ]);
                }
            }
        }

        if (UserActivityLog::count() < 18) {
            foreach ([$users['admin'], $users['staff'], $users['pm']] as $user) {
                for ($i = 1; $i <= 6; $i++) {
                    UserActivityLog::create([
                        'user_id' => $user->id,
                        'activity' => 'seeded_activity_' . $i,
                        'ip_address' => '127.0.0.1',
                        'context' => ['seeded' => true, 'sequence' => $i],
                        'created_at' => Carbon::now()->subDays($i),
                        'updated_at' => Carbon::now()->subDays($i),
                    ]);
                }
            }
        }
    }
}
