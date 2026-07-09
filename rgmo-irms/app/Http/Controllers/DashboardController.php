<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private ReportService $reportService) {}

    /**
     * Display the main administrator dashboard with system-wide statistics.
     *
     * @return View
     */
    public function index()
    {
        $stats = $this->reportService->getDashboardStats();
        $lowStockItems = InventoryItem::lowStock()->limit(5)->get();
        $recentRequests = ResourceRequest::latest()->limit(5)->with('user')->get();
        $recentActivities = AuditLog::latest()->limit(10)->with('user')->get();

        return view('dashboard', [
            'stats' => $stats,
            'lowStockItems' => $lowStockItems,
            'recentRequests' => $recentRequests,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Display the staff-specific dashboard focusing on personal requests.
     *
     * @return View
     */
    public function staff()
    {
        $user = auth()->user();
        $myRequests = $user->requests()->limit(5)->get();
        $stats = [
            'total_requests' => $user->requests()->count(),
            'pending_requests' => $user->requests()->where('status', ResourceRequest::STATUS_PENDING)->count(),
            'approved_requests' => $user->requests()->where('status', ResourceRequest::STATUS_APPROVED)->count(),
        ];

        return view('dashboard.staff', [
            'stats' => $stats,
            'myRequests' => $myRequests,
        ]);
    }

    /**
     * Retrieve the latest dashboard statistics as a JSON response.
     *
     * @return JsonResponse
     */
    public function data()
    {
        $stats = $this->reportService->getDashboardStats();

        return response()->json($stats);
    }

    /**
     * Display operational health across the major RGMO-IRMS modules.
     *
     * @return View
     */
    public function health()
    {
        return view('dashboard.health', [
            'health' => $this->moduleHealthData(),
        ]);
    }

    /**
     * Retrieve module health metrics as JSON for live widgets or external checks.
     *
     * @return JsonResponse
     */
    public function healthData()
    {
        return response()->json($this->moduleHealthData());
    }

    /**
     * Build a compact health snapshot for inventory, requests, security, and audit activity.
     *
     * @return array<string, mixed>
     */
    private function moduleHealthData(): array
    {
        $expiringSoonQuery = InventoryItem::active()
            ->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today(), today()->addDays(30)]);

        $expiredCount = InventoryItem::active()
            ->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->count();

        $moduleStats = [
            'inventory' => [
                'label' => 'Inventory',
                'icon' => 'package',
                'status' => 'healthy',
                'headline' => InventoryItem::active()->count(),
                'headline_label' => 'active items',
                'metrics' => [
                    'Low stock' => InventoryItem::active()->lowStock()->count(),
                    'Warning stock' => InventoryItem::active()->warningStock()->count(),
                    'Expiring in 30 days' => $expiringSoonQuery->count(),
                    'Expired items' => $expiredCount,
                ],
                'route' => route('inventory.low-stock'),
                'action' => 'Review stock',
            ],
            'requests' => [
                'label' => 'Requests',
                'icon' => 'clipboard-list',
                'status' => 'healthy',
                'headline' => ResourceRequest::pending()->count(),
                'headline_label' => 'pending requests',
                'metrics' => [
                    'Approved this week' => ResourceRequest::approved()->where('approved_at', '>=', now()->subDays(7))->count(),
                    'Rejected this week' => ResourceRequest::rejected()->where('rejected_at', '>=', now()->subDays(7))->count(),
                    'Overdue pending' => ResourceRequest::pending()->whereNotNull('needed_date')->whereDate('needed_date', '<', today())->count(),
                    'Submitted today' => ResourceRequest::whereDate('created_at', today())->count(),
                ],
                'route' => route('requests.pending'),
                'action' => 'Open queue',
            ],
            'security' => [
                'label' => 'User Security',
                'icon' => 'shield-alert',
                'status' => 'healthy',
                'headline' => User::where('locked_until', '>', now())->count(),
                'headline_label' => 'locked accounts',
                'metrics' => [
                    'Suspended users' => User::suspended()->count(),
                    'Failed attempts' => User::where('login_attempts', '>', 0)->sum('login_attempts'),
                    'Unverified users' => User::whereNull('email_verified_at')->count(),
                    'Inactive users' => User::inactive()->count(),
                ],
                'route' => route('admin.users.index'),
                'action' => 'Manage users',
            ],
            'audit' => [
                'label' => 'Audit Activity',
                'icon' => 'activity',
                'status' => 'healthy',
                'headline' => AuditLog::whereDate('created_at', today())->count(),
                'headline_label' => 'events today',
                'metrics' => [
                    'Last 7 days' => AuditLog::recent(7)->count(),
                    'Inventory events' => AuditLog::recent(7)->byModule('inventory')->count(),
                    'Request events' => AuditLog::recent(7)->byModule('resource_request')->count(),
                    'User events' => AuditLog::recent(7)->byModule('users')->count(),
                ],
                'route' => route('reports.audit-trail'),
                'action' => 'View audit',
            ],
        ];

        $moduleStats['inventory']['status'] = $this->resolveStatus(
            $moduleStats['inventory']['metrics']['Expired items'] > 0,
            $moduleStats['inventory']['metrics']['Low stock'] > 0 || $moduleStats['inventory']['metrics']['Expiring in 30 days'] > 0
        );
        $moduleStats['requests']['status'] = $this->resolveStatus(
            $moduleStats['requests']['metrics']['Overdue pending'] > 0,
            $moduleStats['requests']['headline'] > 0
        );
        $moduleStats['security']['status'] = $this->resolveStatus(
            $moduleStats['security']['headline'] > 0 || $moduleStats['security']['metrics']['Suspended users'] > 0,
            $moduleStats['security']['metrics']['Failed attempts'] > 0 || $moduleStats['security']['metrics']['Unverified users'] > 0
        );
        $moduleStats['audit']['status'] = $this->resolveStatus(false, $moduleStats['audit']['headline'] === 0);

        $criticalCount = collect($moduleStats)->where('status', 'critical')->count();
        $warningCount = collect($moduleStats)->where('status', 'warning')->count();

        return [
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'overall_status' => $criticalCount > 0 ? 'critical' : ($warningCount > 0 ? 'warning' : 'healthy'),
                'critical_modules' => $criticalCount,
                'warning_modules' => $warningCount,
                'healthy_modules' => collect($moduleStats)->where('status', 'healthy')->count(),
            ],
            'modules' => $moduleStats,
            'urgent' => [
                'low_stock_items' => InventoryItem::active()->lowStock()->orderBy('stock')->limit(5)->get(['id', 'name', 'sku', 'stock', 'unit', 'min_stock']),
                'expiring_items' => $expiringSoonQuery->orderBy('expiry_date')->limit(5)->get(['id', 'name', 'sku', 'expiry_date']),
                'pending_requests' => ResourceRequest::pending()->with('user:id,name')->oldest()->limit(5)->get(['id', 'user_id', 'purpose', 'needed_date', 'created_at']),
                'recent_audit_logs' => AuditLog::with('user:id,name')->latest()->limit(6)->get(['id', 'user_id', 'action', 'module', 'created_at']),
            ],
        ];
    }

    /**
     * Resolve a simple traffic-light status for dashboard cards.
     */
    private function resolveStatus(bool $critical, bool $warning): string
    {
        if ($critical) {
            return 'critical';
        }

        return $warning ? 'warning' : 'healthy';
    }
}
