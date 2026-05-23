<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use App\Models\AuditLog;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reportService)
    {
    }

    /**
     * Display the main administrator dashboard with system-wide statistics.
     *
     * @return \Illuminate\View\View
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
     * @return \Illuminate\View\View
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function data()
    {
        $stats = $this->reportService->getDashboardStats();

        return response()->json($stats);
    }
}