<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Only superadmin and admin can view activity logs
        if ($user->type !== 'superadmin' && $user->type !== 'admin' && $user->type !== 'company') {
            abort(403, 'Unauthorized Access');
        }

        $query = ActivityLog::with(['user'])->latest();

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('browser_agent', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Handle user filter
        if ($request->has('user_id') && !empty($request->user_id) && $request->user_id !== 'all') {
            $query->where('user_id', $request->user_id);
        }

        // Handle type filter
        if ($request->has('type') && !empty($request->type) && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Handle date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Handle pagination
        $perPage = $request->has('per_page') ? (int)$request->per_page : 25;
        $activityLogs = $query->paginate($perPage)->withQueryString();

        // Get unique types for filter dropdown
        $types = ActivityLog::select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->toArray();

        // Get users for filter dropdown (only for superadmin)
        $users = [];
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            $users = User::select('id', 'name', 'email')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'value' => $user->id,
                        'label' => $user->name . ' (' . $user->email . ')'
                    ];
                });
        } else {
            // For company users, show only their company users
            $companyUserIds = getCompanyAndUsersId();
            $users = User::whereIn('id', $companyUserIds)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'value' => $user->id,
                        'label' => $user->name . ' (' . $user->email . ')'
                    ];
                });
        }

        return Inertia::render('admin/activity-logs/index', [
            'activityLogs' => $activityLogs,
            'types' => $types,
            'users' => $users,
            'filters' => $request->only(['search', 'user_id', 'type', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }
}
