<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Run seeding operations without an explicit DB transaction to avoid
        // potential locking issues on SQLite during local development.
            // Primary test user
            User::firstOrCreate([
                'email' => 'test@example.com',
            ], [
                'name' => 'Test User',
                'role' => 'staff',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            // Seed admin and sample accounts for local testing
            User::firstOrCreate([
                'email' => 'admin@example.com',
            ], [
                'name' => 'Admin User',
                'role' => 'admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            User::firstOrCreate([
                'email' => 'staff@example.com',
            ], [
                'name' => 'Staff User',
                'role' => 'staff',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            User::firstOrCreate([
                'email' => 'field@example.com',
            ], [
                'name' => 'Field User',
                'role' => 'field_personnel',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            // System settings
            SystemSetting::set('site_name', 'RGMO RMS Demo');
            SystemSetting::set('default_currency', ['code' => 'PHP', 'symbol' => '₱']);

            // Categories and inventory
            $categories = [
                'Rice Seeds' => 'Biological assets for rice production.',
                'Fertilizers' => 'Agricultural fertilizers.',
                'Chemicals' => 'Agricultural chemicals.',
                'Biological Assets' => 'General biological assets.',
                'Office Supplies' => 'General office consumables and stationery.',
            ];

            $units = ['bag', 'bottle', 'kg', 'hectare', 'head'];
            $fundingSources = ['RGMO', 'DA Grant', 'DA Hybrid'];

            foreach ($categories as $name => $desc) {
                $category = Category::firstOrCreate([
                    'name' => $name,
                ], [
                    'description' => $desc,
                ]);

                // create a few items per category
                for ($i = 1; $i <= 5; $i++) {
                    $itemName = "$name Item $i";
                    if ($name === 'Rice Seeds') {
                        $itemName = ["G3 - RC 226", "GVF 2A - A - RC 222", "NSIC Rc 222", "F3 - RC 440"][$i-1] ?? "$name Item $i";
                    }
                    $sku = Str::upper(Str::slug($name)) . '-' . strtoupper(Str::random(6));

                    $item = InventoryItem::firstOrCreate([
                        'sku' => $sku,
                    ], [
                        'category_id' => $category->id,
                        'name' => $itemName,
                        'sku' => $sku,
                        'stock' => 0, // start with 0 and add via transactions
                        'unit' => Arr::random($units),
                        'min_stock' => rand(2, 20),
                        'reorder_level' => rand(1, 10),
                        'price' => rand(500, 5000) / 10,
                        'description' => "Seeded $itemName for category $name",
                        'planting_date' => $name === 'Biological Assets' || $name === 'Rice Seeds' ? now()->subMonths(rand(1,6)) : null,
                    ]);

                    // initial stock-in transaction per funding source
                    foreach ($fundingSources as $fs) {
                        $qty = rand(10, 50);
                        $item->recordStockIn($qty, 'Initial seed', User::where('email', 'admin@example.com')->value('id'), $fs);
                    }
                }
            }
        
    }
}
