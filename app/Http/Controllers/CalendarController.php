<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Meeting;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\CancelledWeekoff;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->type === 'employee') {
            if (!$user->hasPermissionTo('view-calendar')) {
                abort(403, 'Unauthorized');
            }
        } else {
            if (!$user->hasPermissionTo('manage-calendar') && !$user->hasPermissionTo('view-calendar')) {
                abort(403, 'Unauthorized');
            }
        }

        $companyUserIds = getCompanyAndUsersId();
        $showSaturdayOff = $request->get('show_saturday_off', 1);

        // Meetings
        $meetings = Meeting::query()
            ->when($user->hasRole('employee'), function ($query) use ($user) {
                $query->where('organizer_id', $user->id)
                    ->orWhereHas('attendees', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            }, function ($query) use ($companyUserIds) {
                $query->whereIn('created_by', $companyUserIds);
            })
            ->get()
            ->map(fn($meeting) => [
                'id' => 'meeting-' . $meeting->id,
                'title' => $meeting->title,
                'start' => Carbon::parse($meeting->meeting_date)->format('Y-m-d') . 'T' . Carbon::parse($meeting->start_time)->format('H:i:s'),
                'end'   => Carbon::parse($meeting->meeting_date)->format('Y-m-d') . 'T' . Carbon::parse($meeting->end_time)->format('H:i:s'),
                'type'  => 'meeting',
                'backgroundColor' => '#3b82f6',
                'borderColor'     => '#3b82f6'
            ]);

            


        // Holidays
        $holidays = Holiday::whereIn('created_by', $companyUserIds)->get()->map(fn($holiday) => [
            'id' => 'holiday-' . $holiday->id,
            'title' => $holiday->name,
            'start' => $holiday->start_date,
            'end'   => $holiday->end_date ?: $holiday->start_date,
            'type'  => 'holiday',
            'allDay' => true,
            'backgroundColor' => '#10b981',
            'borderColor'     => '#10b981'
        ]);

        // Leaves
        $leaves = LeaveApplication::whereIn('created_by', $companyUserIds)
            ->where('status', 'approved')
            ->with(['employee', 'leaveType'])
            ->get()
            ->map(fn($leave) => [
                'id' => 'leave-' . $leave->id,
                'title' => $leave->employee->name . ' - ' . $leave->leaveType->name,
                'start' => $leave->start_date,
                'end'   => Carbon::parse($leave->end_date)->addDay()->format('Y-m-d'),
                'type'  => 'leave',
                'allDay' => true,
                'backgroundColor' => '#f59e0b',
                'borderColor'     => '#f59e0b'
            ]);

        // 2nd & 4th Saturday Toggle Logic
        $autoSaturdays = collect();

        if ($showSaturdayOff == 1) {
            $year = now()->year;

            for ($month = 1; $month <= 12; $month++) {
                $date = Carbon::create($year, $month, 1);
                $saturdays = [];

                while ($date->month == $month) {
                    if ($date->isSaturday()) {
                        $saturdays[] = $date->copy();
                    }
                    $date->addDay();
                }

                // 2nd Saturday
                if (isset($saturdays[1])) {
                    $secondDate = $saturdays[1]->format('Y-m-d');

                    if (CancelledWeekoff::where('date', $secondDate)->exists()) {
                        $autoSaturdays->push($this->workingEvent($secondDate, 'Second Saturday'));
                    } else {
                        $autoSaturdays->push($this->weekoffEvent('sat2', $secondDate, 'Second Saturday'));
                    }
                }

                // 4th Saturday
                if (isset($saturdays[3])) {
                    $fourthDate = $saturdays[3]->format('Y-m-d');

                    if (CancelledWeekoff::where('date', $fourthDate)->exists()) {
                        $autoSaturdays->push($this->workingEvent($fourthDate, 'Fourth Saturday'));
                    } else {
                        $autoSaturdays->push($this->weekoffEvent('sat4', $fourthDate, 'Fourth Saturday'));
                    }
                }
            }
        }

        $events = $meetings->concat($holidays)->concat($leaves)->concat($autoSaturdays);

        // Only HR + Admin can edit weekoff/working days
        // Employee ko explicitly exclude karo - sirf view access
        $isEmployee = $user->type === 'employee' || $user->hasRole('employee');
        
        $canEditWeekoff = !$isEmployee && (
            $user->hasRole('hr')
            || $user->hasRole('admin')
            || $user->type === 'admin'
            || $user->type === 'company'  // Company type users are admins
            || $user->hasPermissionTo('edit-weekoff')
            || $user->hasPermissionTo('manage-calendar')  // Users with manage-calendar can edit
        );

        return Inertia::render('calendar/index', [
            'events' => $events,
            'canEditWeekoff' => $canEditWeekoff,
        ]);
    }

    private function weekoffEvent($prefix, $date, $title)
    {
        return [
            'id' => $prefix . '-' . str_replace('-', '', $date),
            'title' => $title . ' (Week Off)',
            'start' => $date,
            'end'   => $date,
            'type'  => 'weekoff',
            'allDay' => true,
            'backgroundColor' => '#ef4444',
            'borderColor'     => '#ef4444'
        ];
    }

    private function workingEvent($date, $title)
    {
        return [
            'id' => 'working-' . str_replace('-', '', $date),
            'title' => $title . ' (Working)',
            'start' => $date,
            'end'   => $date,
            'type'  => 'working',
            'allDay' => true,
            'backgroundColor' => '#22c55e',
            'borderColor'     => '#22c55e'
        ];
    }
public function cancelWeekoff(Request $request)
{
    $user = auth()->user();

    $isEmployee = $user->type === 'employee' || $user->hasRole('employee');
    if ($isEmployee) {
        return response()->json(['message' => 'Forbidden: Employees can only view calendar'], 403);
    }

    if (
        !$user->hasRole('hr')
        && !$user->hasRole('admin')
        && $user->type !== 'admin'
        && $user->type !== 'company'
        && !$user->hasPermissionTo('edit-weekoff')
        && !$user->hasPermissionTo('manage-calendar')
    ) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $request->validate([
        'date' => 'required|date'
    ]);

    CancelledWeekoff::firstOrCreate(
        ['date' => $request->date],
        [
            'created_by' => auth()->id(),
            'reason' => 'Working Saturday'
        ]
    );

    return response()->json(['status' => 'working', 'message' => 'Weekoff cancelled successfully']);
}


public function restoreWeekoff(Request $request)
{
    $user = auth()->user();

    // Employee ko explicitly block karo - sirf view access
    $isEmployee = $user->type === 'employee' || $user->hasRole('employee');
    if ($isEmployee) {
        return response()->json(['message' => 'Forbidden: Employees can only view calendar'], 403);
    }

    // Only HR + Admin can edit weekoff
    if (!$user->hasRole('hr')
        && !$user->hasRole('admin')
        && $user->type !== 'admin'
        && $user->type !== 'company'  // Company type users are admins
        && !$user->hasPermissionTo('edit-weekoff')
        && !$user->hasPermissionTo('manage-calendar')) {  // Users with manage-calendar can edit
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate date
    $request->validate([
        'date' => 'required|date'
    ]);

    CancelledWeekoff::where('date', $request->date)->delete();
    return response()->json(['status' => 'weekoff', 'message' => 'Weekoff restored successfully']);
}

}
