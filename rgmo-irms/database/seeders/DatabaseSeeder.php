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
                'Consumables' => 'Disposable consumable items such as gloves, tapes, and cleaners.',
                'Electronics' => 'Electronic devices and components.',
                'Tools' => 'Hand and power tools used in the field.',
                'Safety Equipment' => 'PPE and safety-related items.',
                'Office Supplies' => 'General office consumables and stationery.',
            ];

            $units = config('inventory.units', ['pcs']);

            foreach ($categories as $name => $desc) {
                $category = Category::firstOrCreate([
                    'name' => $name,
                ], [
                    'description' => $desc,
                ]);

                // create a few items per category
                for ($i = 1; $i <= 3; $i++) {
                    $itemName = "$name Item $i";
                    $sku = Str::upper(Str::slug($name)) . '-' . strtoupper(Str::random(6));

                    $item = InventoryItem::firstOrCreate([
                        'sku' => $sku,
                    ], [
                        'category_id' => $category->id,
                        'name' => $itemName,
                        'sku' => $sku,
                        'stock' => rand(10, 200),
                        'unit' => Arr::random($units),
                        'min_stock' => rand(2, 20),
                        'reorder_level' => rand(1, 10),
                        'price' => rand(100, 5000) / 100,
                        'description' => "Seeded $itemName for category $name",
                    ]);

                    // initial stock-in transaction
                    InventoryTransaction::firstOrCreate([
                        'inventory_item_id' => $item->id,
                        'transaction_type' => 'stock_in',
                    ], [
                        'user_id' => User::where('email', 'admin@example.com')->value('id'),
                        'quantity' => $item->stock,
                        'source' => 'Initial seed',
                    ]);
                }
            }
        
    }
}
