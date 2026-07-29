<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\LoginHistory;
use App\Models\Notification;
use App\Models\Project;
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
                ['sku' => 'SEED-RC160', 'name' => 'NSIC Rc 160', 'unit' => 'bag', 'price' => 1580, 'min_stock' => 18],
                ['sku' => 'SEED-RC480', 'name' => 'NSIC Rc 480', 'unit' => 'bag', 'price' => 1980, 'min_stock' => 14],
                ['sku' => 'SEED-HYB-TH82', 'name' => 'Hybrid Rice TH 82', 'unit' => 'bag', 'price' => 2450, 'min_stock' => 10],
            ],
            'Fertilizers' => [
                ['sku' => 'FERT-UREA', 'name' => 'Urea 46-0-0', 'unit' => 'bag', 'price' => 1450, 'min_stock' => 12],
                ['sku' => 'FERT-16-20', 'name' => 'Complete 16-20-0', 'unit' => 'bag', 'price' => 1680, 'min_stock' => 12],
                ['sku' => 'FERT-14-14', 'name' => 'NPK 14-14-14', 'unit' => 'bag', 'price' => 1740, 'min_stock' => 10],
                ['sku' => 'FERT-21-00', 'name' => 'Ammonium Sulfate 21-0-0', 'unit' => 'bag', 'price' => 1280, 'min_stock' => 14],
                ['sku' => 'FERT-00-00-60', 'name' => 'Muriate of Potash 0-0-60', 'unit' => 'bag', 'price' => 2120, 'min_stock' => 8],
                ['sku' => 'FERT-ORG-COMP', 'name' => 'Organic Compost', 'unit' => 'bag', 'price' => 380, 'min_stock' => 30],
                ['sku' => 'FERT-FOLIAR', 'name' => 'Foliar Fertilizer', 'unit' => 'bottle', 'price' => 450, 'min_stock' => 12],
            ],
            'Chemicals' => [
                ['sku' => 'CHEM-HERB', 'name' => 'Selective Herbicide', 'unit' => 'bottle', 'price' => 620, 'min_stock' => 8, 'expires_in_months' => 10],
                ['sku' => 'CHEM-FUNG', 'name' => 'Systemic Fungicide', 'unit' => 'bottle', 'price' => 710, 'min_stock' => 8, 'expires_in_months' => 6],
                ['sku' => 'CHEM-INSECT', 'name' => 'Insecticide EC', 'unit' => 'bottle', 'price' => 560, 'min_stock' => 6, 'expires_in_months' => 4],
                ['sku' => 'CHEM-MOLL', 'name' => 'Molluscicide Pellets', 'unit' => 'pack', 'price' => 390, 'min_stock' => 10, 'expires_in_months' => 8],
                ['sku' => 'CHEM-BIOPEST', 'name' => 'Bio-Pesticide Concentrate', 'unit' => 'bottle', 'price' => 840, 'min_stock' => 5, 'expires_in_months' => 5],
                ['sku' => 'CHEM-DISINF', 'name' => 'Greenhouse Disinfectant', 'unit' => 'bottle', 'price' => 520, 'min_stock' => 6, 'expires_in_months' => 12],
            ],
            'Office Supplies' => [
                ['sku' => 'OFF-A4', 'name' => 'A4 Bond Paper', 'unit' => 'ream', 'price' => 210, 'min_stock' => 10],
                ['sku' => 'OFF-BALLPEN', 'name' => 'Ballpen Blue', 'unit' => 'box', 'price' => 165, 'min_stock' => 8],
                ['sku' => 'OFF-INK-BLK', 'name' => 'Printer Ink Black', 'unit' => 'bottle', 'price' => 290, 'min_stock' => 6],
                ['sku' => 'OFF-FOLDER', 'name' => 'Expanding Folder', 'unit' => 'pack', 'price' => 135, 'min_stock' => 12],
                ['sku' => 'OFF-TAPE', 'name' => 'Packaging Tape', 'unit' => 'roll', 'price' => 48, 'min_stock' => 20],
            ],
            'Tools and Equipment' => [
                ['sku' => 'TOOL-SPADE', 'name' => 'Garden Spade', 'unit' => 'pcs', 'price' => 520, 'min_stock' => 6],
                ['sku' => 'TOOL-HOE', 'name' => 'Field Hoe', 'unit' => 'pcs', 'price' => 460, 'min_stock' => 6],
                ['sku' => 'TOOL-PRUNER', 'name' => 'Pruning Shears', 'unit' => 'pcs', 'price' => 380, 'min_stock' => 8],
                ['sku' => 'TOOL-SPRAYER-16L', 'name' => 'Knapsack Sprayer 16L', 'unit' => 'pcs', 'price' => 1950, 'min_stock' => 4],
                ['sku' => 'TOOL-WHEELBARROW', 'name' => 'Wheelbarrow', 'unit' => 'pcs', 'price' => 3850, 'min_stock' => 3],
            ],
            'Irrigation Supplies' => [
                ['sku' => 'IRR-HOSE-50M', 'name' => 'Garden Hose 50m', 'unit' => 'roll', 'price' => 1250, 'min_stock' => 5],
                ['sku' => 'IRR-SPRINKLER', 'name' => 'Impact Sprinkler Head', 'unit' => 'pcs', 'price' => 320, 'min_stock' => 12],
                ['sku' => 'IRR-PIPE-1IN', 'name' => 'PVC Pipe 1 inch', 'unit' => 'pcs', 'price' => 180, 'min_stock' => 20],
                ['sku' => 'IRR-VALVE-1IN', 'name' => 'PVC Ball Valve 1 inch', 'unit' => 'pcs', 'price' => 145, 'min_stock' => 15],
                ['sku' => 'IRR-DRIP-KIT', 'name' => 'Drip Irrigation Starter Kit', 'unit' => 'set', 'price' => 2400, 'min_stock' => 4],
            ],
            'PPE and Safety' => [
                ['sku' => 'PPE-GLOVES-NIT', 'name' => 'Nitrile Gloves', 'unit' => 'box', 'price' => 340, 'min_stock' => 12],
                ['sku' => 'PPE-MASK-N95', 'name' => 'N95 Mask', 'unit' => 'box', 'price' => 620, 'min_stock' => 8],
                ['sku' => 'PPE-BOOTS', 'name' => 'Rubber Boots', 'unit' => 'pcs', 'price' => 780, 'min_stock' => 10],
                ['sku' => 'PPE-GOGGLES', 'name' => 'Safety Goggles', 'unit' => 'pcs', 'price' => 180, 'min_stock' => 15],
                ['sku' => 'PPE-RAINCOAT', 'name' => 'Field Raincoat', 'unit' => 'pcs', 'price' => 560, 'min_stock' => 8],
            ],
            'Nursery Materials' => [
                ['sku' => 'NURS-TRAY-104', 'name' => 'Seedling Tray 104 Holes', 'unit' => 'pcs', 'price' => 95, 'min_stock' => 40],
                ['sku' => 'NURS-POT-6IN', 'name' => 'Poly Pot 6 inch', 'unit' => 'pack', 'price' => 230, 'min_stock' => 25],
                ['sku' => 'NURS-COCOPEAT', 'name' => 'Cocopeat Growing Medium', 'unit' => 'bag', 'price' => 480, 'min_stock' => 18],
                ['sku' => 'NURS-SHADE-NET', 'name' => 'Shade Net 50 Percent', 'unit' => 'roll', 'price' => 2850, 'min_stock' => 3],
                ['sku' => 'NURS-LABEL-STICK', 'name' => 'Plant Label Stick', 'unit' => 'pack', 'price' => 120, 'min_stock' => 15],
            ],
            'Fuel and Lubricants' => [
                ['sku' => 'FUEL-DIESEL', 'name' => 'Diesel Fuel', 'unit' => 'l', 'price' => 68, 'min_stock' => 120],
                ['sku' => 'FUEL-GASOLINE', 'name' => 'Gasoline', 'unit' => 'l', 'price' => 72, 'min_stock' => 80],
                ['sku' => 'LUBE-2T', 'name' => '2T Engine Oil', 'unit' => 'bottle', 'price' => 185, 'min_stock' => 12],
                ['sku' => 'LUBE-GREASE', 'name' => 'Multi-Purpose Grease', 'unit' => 'pack', 'price' => 160, 'min_stock' => 10],
            ],
            'Packaging and Labels' => [
                ['sku' => 'PKG-SACK-50KG', 'name' => 'Rice Sack 50kg', 'unit' => 'pcs', 'price' => 28, 'min_stock' => 100],
                ['sku' => 'PKG-TWINE', 'name' => 'Plastic Twine', 'unit' => 'roll', 'price' => 95, 'min_stock' => 20],
                ['sku' => 'PKG-ZIPBAG', 'name' => 'Seed Sample Zip Bag', 'unit' => 'pack', 'price' => 150, 'min_stock' => 18],
                ['sku' => 'PKG-WATERPROOF-LABEL', 'name' => 'Waterproof Inventory Label', 'unit' => 'roll', 'price' => 420, 'min_stock' => 8],
            ],
            'Field Supplies' => [
                ['sku' => 'FIELD-MARKER', 'name' => 'Field Marker Stake', 'unit' => 'pcs', 'price' => 35, 'min_stock' => 80],
                ['sku' => 'FIELD-ROPE', 'name' => 'Nylon Field Rope', 'unit' => 'roll', 'price' => 260, 'min_stock' => 12],
                ['sku' => 'FIELD-TARP-6X8', 'name' => 'Tarpaulin 6x8', 'unit' => 'pcs', 'price' => 620, 'min_stock' => 6],
                ['sku' => 'FIELD-MEASURE-TAPE', 'name' => 'Measuring Tape 50m', 'unit' => 'pcs', 'price' => 540, 'min_stock' => 5],
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
                        'has_expiry' => isset($product['expires_in_months']),
                        'expiry_date' => isset($product['expires_in_months']) ? now()->addMonths($product['expires_in_months'])->toDateString() : null,
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

        $projects = collect([
            Project::updateOrCreate(
                ['code' => 'PRJ-RICE-2026'],
                [
                    'name' => 'Rice Seed Production Program',
                    'status' => Project::STATUS_ACTIVE,
                    'start_date' => now()->subMonths(4)->toDateString(),
                    'end_date' => now()->addMonths(3)->toDateString(),
                    'description' => 'Tracks seed, fertilizer, and field inputs used by the rice production program.',
                ]
            ),
            Project::updateOrCreate(
                ['code' => 'PRJ-FIELD-OPS'],
                [
                    'name' => 'Field Operations Support',
                    'status' => Project::STATUS_ACTIVE,
                    'start_date' => now()->subMonths(2)->toDateString(),
                    'end_date' => null,
                    'description' => 'Operational support project for supplies distributed to field teams.',
                ]
            ),
            Project::updateOrCreate(
                ['code' => 'PRJ-NURSERY-2026'],
                [
                    'name' => 'Nursery Propagation Expansion',
                    'status' => Project::STATUS_ON_HOLD,
                    'start_date' => now()->subMonth()->toDateString(),
                    'end_date' => now()->addMonths(5)->toDateString(),
                    'description' => 'Demo nursery expansion project for tracking seeds, chemicals, and staff utilization.',
                ]
            ),
            Project::updateOrCreate(
                ['code' => 'PRJ-DEMO-CLOSE'],
                [
                    'name' => 'Closed Demonstration Plot',
                    'status' => Project::STATUS_COMPLETED,
                    'start_date' => now()->subMonths(8)->toDateString(),
                    'end_date' => now()->subMonth()->toDateString(),
                    'description' => 'Completed sample project with historical resource usage records.',
                ]
            ),
        ]);

        $projects->each(function (Project $project, int $index) use ($users) {
            $project->managers()->syncWithoutDetaching([$users['pm']->id]);
        });

        $projectUsagePlan = [
            'PRJ-RICE-2026' => [
                ['sku' => 'SEED-RC226', 'quantity' => 18, 'field' => 'RICE-FIELD-01', 'days_ago' => 21],
                ['sku' => 'FERT-UREA', 'quantity' => 10, 'field' => 'RICE-FIELD-01', 'days_ago' => 14],
                ['sku' => 'CHEM-HERB', 'quantity' => 6, 'field' => 'RICE-FIELD-02', 'days_ago' => 7],
            ],
            'PRJ-FIELD-OPS' => [
                ['sku' => 'OFF-A4', 'quantity' => 5, 'field' => 'OPS-STATION-01', 'days_ago' => 18],
                ['sku' => 'FERT-16-20', 'quantity' => 8, 'field' => 'FIELD-OPS-03', 'days_ago' => 11],
                ['sku' => 'CHEM-INSECT', 'quantity' => 4, 'field' => 'FIELD-OPS-04', 'days_ago' => 5],
            ],
            'PRJ-NURSERY-2026' => [
                ['sku' => 'SEED-RC440', 'quantity' => 12, 'field' => 'NURSERY-BED-02', 'days_ago' => 16],
                ['sku' => 'FERT-14-14', 'quantity' => 7, 'field' => 'NURSERY-BED-03', 'days_ago' => 8],
            ],
            'PRJ-DEMO-CLOSE' => [
                ['sku' => 'SEED-NSIC222', 'quantity' => 20, 'field' => 'DEMO-PLOT-01', 'days_ago' => 74],
                ['sku' => 'FERT-UREA', 'quantity' => 9, 'field' => 'DEMO-PLOT-01', 'days_ago' => 62],
                ['sku' => 'CHEM-FUNG', 'quantity' => 3, 'field' => 'DEMO-PLOT-01', 'days_ago' => 45],
            ],
        ];

        $itemsBySku = $items->keyBy('sku');
        $projectsByCode = $projects->keyBy('code');

        foreach ($projectUsagePlan as $projectCode => $usageRows) {
            $project = $projectsByCode->get($projectCode);

            if (! $project || $project->resourceUsages()->where('notes', 'like', 'Seeded project dummy usage:%')->exists()) {
                continue;
            }

            foreach ($usageRows as $offset => $usageRow) {
                $item = $itemsBySku->get($usageRow['sku']);

                if (! $item) {
                    continue;
                }

                $usedAt = now()->subDays($usageRow['days_ago'])->setHour(10 + $offset);

                ResourceUsage::create([
                    'inventory_item_id' => $item->id,
                    'user_id' => $users['pm']->id,
                    'project_id' => $project->id,
                    'field_id' => $usageRow['field'],
                    'quantity' => $usageRow['quantity'],
                    'notes' => 'Seeded project dummy usage: ' . $projectCode,
                    'created_at' => $usedAt,
                    'updated_at' => $usedAt,
                ]);
            }
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
                    'project_id' => $projects->random()->id,
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
                            'project_id' => $projects->random()->id,
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
