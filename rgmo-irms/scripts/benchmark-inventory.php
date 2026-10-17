<?php

// Run from the project root: php scripts/benchmark-inventory.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite') {
    throw new RuntimeException('This benchmark supports local SQLite snapshots only.');
}
$source = realpath(config('database.connections.sqlite.database'));
if (! $source || $source === ':memory:') {
    throw new RuntimeException('A file-backed source database is required.');
}
$target = sys_get_temp_dir().'/rgmo-inventory-benchmark-'.date('Ymd-His').'-'.getmypid().'.sqlite';
$pdo = new PDO('sqlite:'.$source);
$pdo->exec('VACUUM INTO '.$pdo->quote($target));
$pdo = null;
config(['database.connections.sqlite.database' => $target]);
DB::purge('sqlite');
DB::disableQueryLog();
$report = [
    'database' => $target,
    'generated_records' => 1000000,
    'scope' => 'Single-process local SQLite inventory service queries; no browser, concurrent users, full CSV import, or deployment test.',
    'php_version' => PHP_VERSION,
];
$service = $app->make(InventoryService::class);
$page = 1;
Paginator::currentPageResolver(function () use (&$page) { return $page; });
$cases = [
    'inventory_first_page' => [1, []],
    'inventory_deep_page' => [125000, []],
    'search_matching' => [1, ['search' => 'Load item 000']],
    'search_no_match' => [1, ['search' => 'NONEXISTENT-BENCHMARK']],
    'low_stock_page' => [1, ['status' => 'low']],
    'sort_stock' => [1, ['sort' => 'stock', 'direction' => 'desc']],
];
$measure = function () use ($service, $cases, &$page) {
    $results = [];
    foreach ($cases as $name => [$requestedPage, $filters]) {
        $page = $requestedPage;
        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            $items = $service->getAllItems(8, $filters);
            $times[] = round((hrtime(true) - $start) / 1e6, 2);
        }
        $sorted = $times;
        sort($sorted);
        $results[$name] = ['runs_ms' => $times, 'median_ms' => $sorted[2], 'max_ms' => max($times), 'total_matches' => $items->total(), 'returned' => $items->count()];
        echo $name.': '.$sorted[2]." ms median\n";
    }
    return $results;
};
$report['initial_records'] = InventoryItem::withTrashed()->count();
echo "Baseline\n";
$report['baseline'] = $measure();
$categoryId = DB::table('categories')->value('id');
if (! $categoryId) {
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Benchmark category', 'created_at' => now(), 'updated_at' => now()]);
}
$prefix = 'BENCH-'.bin2hex(random_bytes(4)).'-';
$stamp = now()->toDateTimeString();
$start = hrtime(true);
for ($offset = 0; $offset < 1000000; $offset += 500) {
    if (disk_free_space(dirname($target)) < 100 * 1024 * 1024) {
        throw new RuntimeException('Stopped because less than 100 MB disk space remains. Snapshot: '.$target);
    }
    $rows = [];
    for ($i = $offset; $i < $offset + 500; $i++) {
        $number = str_pad((string) $i, 7, '0', STR_PAD_LEFT);
        $rows[] = ['category_id' => $categoryId, 'name' => 'Load item '.$number, 'sku' => $prefix.$number, 'stock' => $i % 101, 'unit' => 'pcs', 'min_stock' => 10, 'description' => 'Synthetic benchmark item', 'created_at' => $stamp, 'updated_at' => $stamp];
    }
    DB::table('inventory_items')->insert($rows);
    if (($offset + 500) % 100000 === 0) {
        echo 'Inserted '.($offset + 500)."\n";
    }
}
$report['insertion_seconds'] = round((hrtime(true) - $start) / 1e9, 2);
$report['final_records'] = InventoryItem::withTrashed()->count();
if ($report['final_records'] !== $report['initial_records'] + 1000000) {
    throw new RuntimeException('Unexpected final inventory count.');
}
echo "One million added\n";
$report['loaded'] = $measure();
$report['query_plans'] = [
    'default_list' => DB::select('EXPLAIN QUERY PLAN SELECT * FROM inventory_items WHERE deleted_at IS NULL ORDER BY name, id LIMIT 8'),
    'search' => DB::select("EXPLAIN QUERY PLAN SELECT * FROM inventory_items WHERE deleted_at IS NULL AND (name LIKE '%NONEXISTENT-BENCHMARK%' OR sku LIKE '%NONEXISTENT-BENCHMARK%' OR description LIKE '%NONEXISTENT-BENCHMARK%') ORDER BY name, id LIMIT 8"),
];
$report['peak_php_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
$report['database_size_mb'] = round(filesize($target) / 1048576, 2);
$report['completed_at'] = now()->toIso8601String();
file_put_contents(dirname(__DIR__).'/docs/performance/inventory-million-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo 'Results: docs/performance/inventory-million-results.json'."\nSnapshot: ".$target."\n";
