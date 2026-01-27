<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Announcement::withPermissionCheck()->with(['departments', 'branches']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        // Handle category filter
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        // Handle department filter
        if ($request->has('department_id') && !empty($request->department_id)) {
            $query->where(function ($q) use ($request) {
                $q->where('is_company_wide', true)
                    ->orWhereHas('departments', function ($q) use ($request) {
                        $q->where('departments.id', $request->department_id);
                    });
            });
        }

        // Handle branch filter
        if ($request->has('branch_id') && !empty($request->branch_id)) {
            $query->where(function ($q) use ($request) {
                $q->where('is_company_wide', true)
                    ->orWhereHas('branches', function ($q) use ($request) {
                        $q->where('branches.id', $request->branch_id);
                    });
            });
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status)) {
            $today = now()->format('Y-m-d');

            if ($request->status === 'active') {
                $query->where('start_date', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $today);
                    });
            } elseif ($request->status === 'upcoming') {
                $query->where('start_date', '>', $today);
            } elseif ($request->status === 'expired') {
                $query->whereNotNull('end_date')
                    ->where('end_date', '<', $today);
            }
        }

        // Handle priority filter
        if ($request->has('priority') && !empty($request->priority)) {
            if ($request->priority === 'high') {
                $query->where('is_high_priority', true);
            } elseif ($request->priority === 'normal') {
                $query->where('is_high_priority', false);
            }
        }

        // Handle featured filter
        if ($request->has('featured') && $request->featured === 'true') {
            $query->where('is_featured', true);
        }

        // Handle date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where(function ($q) use ($request) {
                $q->where('start_date', '>=', $request->date_from)
                    ->orWhere('end_date', '>=', $request->date_from);
            });
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where(function ($q) use ($request) {
                $q->where('start_date', '<=', $request->date_to)
                    ->orWhere('end_date', '<=', $request->date_to);
            });
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $announcements = $query->paginate($request->per_page ?? 10);

        // Get departments for filter dropdown
        $departments = Department::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'name')
            ->get();

        // Get branches for filter dropdown
        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'name')
            ->get();

        // Get categories for filter dropdown
        $categories = Announcement::whereIn('created_by', getCompanyAndUsersId())
            ->select('category')
            ->distinct()
            ->pluck('category')
            ->toArray();

        return Inertia::render('hr/announcements/index', [
            'announcements' => $announcements,
            'departments' => $departments,
            'branches' => $branches,
            'categories' => $categories,
            'filters' => $request->all(['search', 'category', 'department_id', 'branch_id', 'status', 'priority', 'featured', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    /**
     * Display the dashboard view.
     */
    public function dashboard(Request $request)
    {
        $today = now()->format('Y-m-d');

        // Get all announcements (active, expired, upcoming)
        $allAnnouncements = Announcement::with(['departments', 'branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->orderBy('is_high_priority', 'desc')
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get featured announcements (from all announcements)
        $featuredAnnouncements = Announcement::with(['departments', 'branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get high priority announcements (from all announcements)
        $highPriorityAnnouncements = Announcement::with(['departments', 'branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('is_high_priority', true)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get upcoming announcements
        $upcomingAnnouncements = Announcement::with(['departments', 'branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('start_date', '>', $today)
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->get();

        // Get categories for filter
        $categories = Announcement::whereIn('created_by', getCompanyAndUsersId())
            ->select('category')
            ->distinct()
            ->pluck('category')
            ->toArray();

        // Get departments for filter
        $departments = Department::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'name')
            ->get();

        // Get branches for filter
        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'name')
            ->get();

        // Get employee for marking announcements as read
        $employee = null;
        if (Auth::user()->type !== 'company' && Auth::user()->type !== 'superadmin') {
            $employee = User::where('id', Auth::id())->first();
        }

        return Inertia::render('hr/announcements/dashboard', [
            'allAnnouncements' => $allAnnouncements,
            'featuredAnnouncements' => $featuredAnnouncements,
            'highPriorityAnnouncements' => $highPriorityAnnouncements,
            'upcomingAnnouncements' => $upcomingAnnouncements,
            'categories' => $categories,
            'departments' => $departments,
            'branches' => $branches,
            'employee' => $employee,
            'filters' => $request->all(['category', 'department_id', 'branch_id']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'attachments' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'is_high_priority' => 'nullable|boolean',
            'is_company_wide' => 'nullable|boolean',
            'department_ids' => 'nullable|string|required_if:is_company_wide,false',
            'branch_ids' => 'nullable|string|required_if:is_company_wide,false',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Check if departments and branches belong to current company
        if (
            !$request->is_company_wide &&
            (empty($request->department_ids) && empty($request->branch_ids))
        ) {
            return redirect()->back()->with('error', 'You must select at least one department or branch if the announcement is not company-wide');
        }

        if (!empty($request->department_ids)) {
            $validDepartment = Department::where('created_by', createdBy())
                ->where('id', $request->department_ids)
                ->exists();

            if (!$validDepartment) {
                return redirect()->back()->with('error', 'Invalid department selection');
            }
        }

        if (!empty($request->branch_ids)) {
            $validBranch = Branch::where('created_by', createdBy())
                ->where('id', $request->branch_ids)
                ->exists();

            if (!$validBranch) {
                return redirect()->back()->with('error', 'Invalid branch selection');
            }
        }

        $announcementData = [
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'content' => $request->content,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_featured' => $request->is_featured ?? false,
            'is_high_priority' => $request->is_high_priority ?? false,
            'is_company_wide' => $request->is_company_wide ?? true,
            'created_by' => creatorId(),
        ];

        // Handle attachment from media library
        if ($request->has('attachments')) {
            $announcementData['attachments'] = $request->attachments;
        }

        $announcement = Announcement::create($announcementData);

        // Attach departments and branches if not company-wide
        if (!$request->is_company_wide) {
            if (!empty($request->department_ids)) {
                $announcement->departments()->attach([$request->department_ids]);
            }

            if (!empty($request->branch_ids)) {
                $announcement->branches()->attach([$request->branch_ids]);
            }
        }

        // Send email notifications to employees
        // Reload announcement with relationships
        $announcement->load(['departments', 'branches']);
        $this->sendAnnouncementEmails($announcement);

        return redirect()->back()->with('success', __('Announcement created successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement)
    {
        // Check if announcement belongs to current company
        if (!in_array($announcement->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to view this announcement'));
        }

        // Load relationships
        $announcement->load(['departments', 'branches']);

        // Get view statistics
        $viewCount = $announcement->viewedBy()->count();
        $totalEmployees = User::where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->count();
        $viewPercentage = $totalEmployees > 0 ? round(($viewCount / $totalEmployees) * 100) : 0;

        // Mark as viewed if current user is an employee
        if (Auth::user()->type !== 'company' && Auth::user()->type !== 'superadmin') {
            $employee = User::where('id', Auth::id())->first();

            if ($employee) {
                // Check if already viewed
                $existingView = AnnouncementView::where('announcement_id', $announcement->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                if (!$existingView) {
                    // Mark as viewed
                    $announcement->viewedBy()->attach($employee->id, [
                        'viewed_at' => now()
                    ]);
                }
            }
        }

        return Inertia::render('hr/announcements/show', [
            'announcement' => $announcement,
            'viewCount' => $viewCount,
            'totalEmployees' => $totalEmployees,
            'viewPercentage' => $viewPercentage,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement)
    {
        // Check if announcement belongs to current company
        if (!in_array($announcement->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to update this announcement');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'attachments' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'is_high_priority' => 'nullable|boolean',
            'is_company_wide' => 'nullable|boolean',
            'department_ids' => 'nullable|string|required_if:is_company_wide,false',
            'branch_ids' => 'nullable|string|required_if:is_company_wide,false',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Check if departments and branches belong to current company
        if (
            !$request->is_company_wide &&
            (empty($request->department_ids) && empty($request->branch_ids))
        ) {
            return redirect()->back()->with('error', 'You must select at least one department or branch if the announcement is not company-wide');
        }

        if (!empty($request->department_ids)) {
            $departmentId = $request->department_ids;
            $validDepartment = Department::where('created_by', createdBy())
                ->where('id', $departmentId)
                ->exists();

            if (!$validDepartment) {
                return redirect()->back()->with('error', 'Invalid department selection');
            }
        }

        if (!empty($request->branch_ids)) {
            $branchId = $request->branch_ids;
            $validBranch = Branch::where('created_by', createdBy())
                ->where('id', $branchId)
                ->exists();

            if (!$validBranch) {
                return redirect()->back()->with('error', 'Invalid branch selection');
            }
        }

        $announcementData = [
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'content' => $request->content,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_featured' => $request->is_featured ?? false,
            'is_high_priority' => $request->is_high_priority ?? false,
            'is_company_wide' => $request->is_company_wide ?? true,
        ];

        // Handle attachment from media library
        if ($request->has('attachments')) {
            $announcementData['attachments'] = $request->attachments;
        }

        $announcement->update($announcementData);

        // Sync departments and branches
        if ($request->is_company_wide) {
            $announcement->departments()->detach();
            $announcement->branches()->detach();
        } else {
            if (!empty($request->department_ids)) {
                $announcement->departments()->sync([$request->department_ids]);
            } else {
                $announcement->departments()->detach();
            }

            if (!empty($request->branch_ids)) {
                $announcement->branches()->sync([$request->branch_ids]);
            } else {
                $announcement->branches()->detach();
            }
        }

        return redirect()->back()->with('success', __('Announcement updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announcement $announcement)
    {
        // Check if announcement belongs to current company
        if (!in_array($announcement->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to delete this announcement');
        }

        // Detach all departments and branches
        $announcement->departments()->detach();
        $announcement->branches()->detach();

        // Delete all views
        $announcement->viewedBy()->detach();

        // Delete the announcement
        $announcement->delete();

        return redirect()->back()->with('success', __('Announcement deleted successfully'));
    }

    /**
     * Download attachment file.
     */
    public function downloadAttachment(Announcement $announcement)
    {
        // Check if announcement belongs to current company
        if (!in_array($announcement->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to access this attachment'));
        }

        if (!$announcement->attachments) {
            return redirect()->back()->with('error', __('Attachment file not found'));
        }

        $filePath = getStorageFilePath($announcement->attachments);
        
        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', __('Attachment file not found'));
        }
        
        return response()->download($filePath);
    }

    /**
     * Mark announcement as read for current employee.
     */
    public function markAsRead(Request $request, Announcement $announcement)
    {
        $employee = User::where('id', Auth::id())->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Check if already viewed
        $existingView = AnnouncementView::where('announcement_id', $announcement->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$existingView) {
            // Mark as viewed
            $announcement->viewedBy()->attach($employee->id, [
                'viewed_at' => now()
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get announcement view statistics.
     */
    public function viewStatistics(Announcement $announcement)
    {
        // Check if announcement belongs to current company
        if (!in_array($announcement->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to view these statistics'));
        }

        // Load viewed by (employees)
        $views = $announcement->viewedBy()->get();

        // Get total employees
        $totalEmployees = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->count();

        // Get statistics only for announcement's target branch and department
        $departmentStats = [];
        $branchStats = [];

        if (!$announcement->is_company_wide) {
            // Get target branch and department
            $targetBranch = $announcement->branches->first();
            $targetDepartment = $announcement->departments->first();

            if ($targetBranch) {
                $branchEmployees = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->whereHas('employee', function ($q) use ($targetBranch) {
                    $q->where('branch_id', $targetBranch->id);
                })->count();
                $branchViews = $announcement->viewedBy()
                    ->whereHas('employee', function ($q) use ($targetBranch) {
                        $q->where('branch_id', $targetBranch->id);
                    })
                    ->count();

                $branchStats[] = [
                    'branch' => $targetBranch->name,
                    'total' => $branchEmployees,
                    'viewed' => $branchViews,
                    'percentage' => $branchEmployees > 0 ? round(($branchViews / $branchEmployees) * 100) : 0
                ];
            }

            if ($targetDepartment) {
                $departmentEmployees = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->whereHas('employee', function ($q) use ($targetDepartment) {
                    $q->where('department_id', $targetDepartment->id);
                })->count();

                $departmentViews = $announcement->viewedBy()
                    ->whereHas('employee', function ($q) use ($targetDepartment) {
                        $q->where('department_id', $targetDepartment->id);
                    })
                    ->count();

                $departmentStats[] = [
                    'branch_name' => $targetBranch ? $targetBranch->name : 'Unknown',
                    'departments' => [[
                        'department' => $targetDepartment->name,
                        'total' => $departmentEmployees,
                        'viewed' => $departmentViews,
                        'percentage' => $departmentEmployees > 0 ? round(($departmentViews / $departmentEmployees) * 100) : 0
                    ]]
                ];
            }
        }

        return Inertia::render('hr/announcements/statistics', [
            'announcement' => $announcement,
            'totalEmployees' => $totalEmployees,
            'viewedCount' => $views->count(),
            'viewPercentage' => $totalEmployees > 0 ? round(($views->count() / $totalEmployees) * 100) : 0,
            'departmentStats' => $departmentStats,
            'branchStats' => $branchStats,
        ]);
    }

    /**
     * Get departments based on selected branches.
     */
    public function getDepartments($branchIds)
    {
        $branchIdArray = explode(',', $branchIds);

        $departments = Department::whereIn('branch_id', $branchIdArray)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'name')
            ->get()
            ->map(function ($dept) {
                return [
                    'value' => $dept->id,
                    'label' => $dept->name
                ];
            });
        return response()->json($departments);
    }

    /**
     * Send email notifications to employees for the announcement.
     */
    private function sendAnnouncementEmails(Announcement $announcement)
    {
        try {
            // Configure SMTP settings before sending emails
            MailConfigService::setDynamicConfig();

            // Get employees who should receive this announcement
            $employees = $this->getTargetEmployees($announcement);

            if ($employees->isEmpty()) {
                Log::info('No employees found to send announcement email', ['announcement_id' => $announcement->id]);
                return;
            }

            Log::info('Sending announcement emails', [
                'announcement_id' => $announcement->id,
                'total_employees' => $employees->count(),
                'is_company_wide' => $announcement->is_company_wide
            ]);

            $fromEmail = getSetting('email_from_address') ?: config('mail.from.address');
            $fromName = getSetting('email_from_name') ?: config('app.name');

            // Prepare email content
            $subject = $announcement->is_high_priority 
                ? '🔴 High Priority: ' . $announcement->title 
                : '📢 ' . $announcement->title;

            $appUrl = config('app.url');
            $announcementUrl = $appUrl . '/hr/announcements/' . $announcement->id;

            $successCount = 0;
            $failureCount = 0;

            // Send email to each employee
            foreach ($employees as $employee) {
                try {
                    // Skip if email is empty or invalid
                    if (empty($employee->email) || !filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
                        Log::warning('Skipping employee with invalid email', [
                            'announcement_id' => $announcement->id,
                            'employee_id' => $employee->id,
                            'employee_email' => $employee->email
                        ]);
                        $failureCount++;
                        continue;
                    }

                    $emailContent = $this->buildAnnouncementEmailContent($announcement, $employee, $announcementUrl);

                    Mail::send([], [], function ($message) use ($employee, $subject, $emailContent, $fromEmail, $fromName) {
                        $message->to($employee->email, $employee->name)
                            ->subject($subject)
                            ->html($emailContent)
                            ->from($fromEmail, $fromName);
                    });

                    $successCount++;
                    Log::info('Announcement email sent successfully', [
                        'announcement_id' => $announcement->id,
                        'employee_id' => $employee->id,
                        'employee_email' => $employee->email,
                        'employee_name' => $employee->name
                    ]);
                } catch (\Exception $e) {
                    $failureCount++;
                    Log::error('Failed to send announcement email to employee', [
                        'announcement_id' => $announcement->id,
                        'employee_id' => $employee->id,
                        'employee_email' => $employee->email,
                        'employee_name' => $employee->name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue sending to other employees even if one fails
                }
            }

            Log::info('Announcement email sending completed', [
                'announcement_id' => $announcement->id,
                'total_employees' => $employees->count(),
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send announcement emails', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get target employees for the announcement based on scope.
     */
    private function getTargetEmployees(Announcement $announcement)
    {
        $companyUserIds = getCompanyAndUsersId();
        
        // Base query for all employees
        $query = User::where('type', 'employee')
            ->whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        // If company-wide, return all employees
        if ($announcement->is_company_wide) {
            $employees = $query->get();
            Log::info('Company-wide announcement - fetching all employees', [
                'announcement_id' => $announcement->id,
                'total_employees' => $employees->count()
            ]);
            return $employees;
        }

        // If not company-wide, filter by departments and branches
        $query->where(function ($q) use ($announcement) {
            $hasCondition = false;

            // Check if employee belongs to any of the announcement's departments
            if ($announcement->departments->isNotEmpty()) {
                $departmentIds = $announcement->departments->pluck('id')->toArray();
                $q->whereHas('employee', function ($eq) use ($departmentIds) {
                    $eq->whereIn('department_id', $departmentIds);
                });
                $hasCondition = true;
            }

            // Check if employee belongs to any of the announcement's branches
            if ($announcement->branches->isNotEmpty()) {
                $branchIds = $announcement->branches->pluck('id')->toArray();
                if ($hasCondition) {
                    $q->orWhereHas('employee', function ($eq) use ($branchIds) {
                        $eq->whereIn('branch_id', $branchIds);
                    });
                } else {
                    $q->whereHas('employee', function ($eq) use ($branchIds) {
                        $eq->whereIn('branch_id', $branchIds);
                    });
                }
            }
        });

        $employees = $query->get();
        Log::info('Department/Branch specific announcement - fetching filtered employees', [
            'announcement_id' => $announcement->id,
            'total_employees' => $employees->count(),
            'department_ids' => $announcement->departments->pluck('id')->toArray(),
            'branch_ids' => $announcement->branches->pluck('id')->toArray()
        ]);
        
        return $employees;
    }

    /**
     * Build HTML email content for the announcement.
     */
    private function buildAnnouncementEmailContent(Announcement $announcement, User $employee, string $announcementUrl): string
    {
        $priorityBadge = $announcement->is_high_priority 
            ? '<span style="background-color: #ef4444; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">HIGH PRIORITY</span>'
            : '';

        $featuredBadge = $announcement->is_featured 
            ? '<span style="background-color: #3b82f6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">FEATURED</span>'
            : '';

        $badges = trim($priorityBadge . ' ' . $featuredBadge);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h1 style="color: #1f2937; margin-top: 0;">' . htmlspecialchars($announcement->title) . '</h1>
                ' . ($badges ? '<div style="margin-bottom: 15px;">' . $badges . '</div>' : '') . '
                <p style="color: #6b7280; margin: 10px 0;">
                    <strong>Category:</strong> ' . htmlspecialchars($announcement->category) . '<br>
                    <strong>Start Date:</strong> ' . $announcement->start_date->format('F d, Y') . '<br>
                    ' . ($announcement->end_date ? '<strong>End Date:</strong> ' . $announcement->end_date->format('F d, Y') . '<br>' : '') . '
                </p>
            </div>

            ' . ($announcement->description ? '
            <div style="background-color: #ffffff; padding: 15px; border-left: 4px solid #3b82f6; margin-bottom: 20px;">
                <div style="margin: 0; font-style: italic; color: #4b5563;">' . $this->cleanHtmlContent($announcement->description) . '</div>
            </div>
            ' : '') . '

            <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px;">
                <div style="color: #374151;">
                    ' . $this->cleanHtmlContent($announcement->content) . '
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="' . htmlspecialchars($announcementUrl) . '" 
                   style="display: inline-block; background-color: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                    View Full Announcement
                </a>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
                <p>This is an automated notification from ' . htmlspecialchars(config('app.name')) . '</p>
                <p>You are receiving this because you are an employee in the organization.</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Clean and properly format HTML content for email.
     * Converts HTML tags to proper formatting while preserving structure.
     */
    private function cleanHtmlContent($content)
    {
        if (empty($content)) {
            return '';
        }

        // Remove any script tags and dangerous HTML for security
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $content);
        $content = preg_replace('/on\w+="[^"]*"/i', '', $content);
        $content = preg_replace('/on\w+=\'[^\']*\'/i', '', $content);
        $content = preg_replace('/javascript:/i', '', $content);

        // If content already contains HTML tags, use them directly with proper styling
        if (strip_tags($content) !== $content) {
            // Content has HTML tags, ensure proper paragraph formatting
            $content = preg_replace('/<p[^>]*>/i', '<p style="margin: 10px 0; color: #374151; line-height: 1.6;">', $content);
            $content = preg_replace('/<br\s*\/?>/i', '<br style="line-height: 1.6;">', $content);
            // Ensure other common tags have proper styling
            $content = preg_replace('/<strong>/i', '<strong style="font-weight: bold;">', $content);
            $content = preg_replace('/<b>/i', '<b style="font-weight: bold;">', $content);
            $content = preg_replace('/<em>/i', '<em style="font-style: italic;">', $content);
            $content = preg_replace('/<i>/i', '<i style="font-style: italic;">', $content);
            return $content;
        } else {
            // Plain text, convert newlines to <br> and wrap in paragraphs
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            $content = nl2br($content);
            return '<p style="margin: 10px 0; color: #374151; line-height: 1.6;">' . $content . '</p>';
        }
    }
}
