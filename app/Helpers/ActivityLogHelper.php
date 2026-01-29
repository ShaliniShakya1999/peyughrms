<?php

namespace App\Helpers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityLogHelper
{
    /**
     * Log user activity
     * 
     * @param string $type Activity type (e.g., 'login', 'logout', 'create_user', 'update_employee', etc.)
     * @param int|null $userId Optional user ID (defaults to current authenticated user)
     * @return ActivityLog
     */
    public static function log(string $type, ?int $userId = null): ?ActivityLog
    {
        try {
            $userId = $userId ?? Auth::id();
            
            if (!$userId) {
                return null;
            }

            return ActivityLog::create([
                'user_id' => $userId,
                'type' => $type,
                'ip_address' => request()->ip() ?? '0.0.0.0',
                'browser_agent' => request()->userAgent() ?? 'Unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('Activity log failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Log login activity
     */
    public static function logLogin(?int $userId = null): ?ActivityLog
    {
        return self::log('login', $userId);
    }

    /**
     * Log logout activity
     */
    public static function logLogout(?int $userId = null): ?ActivityLog
    {
        return self::log('logout', $userId);
    }

    /**
     * Log user creation
     */
    public static function logUserCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_user', $userId);
    }

    /**
     * Log user update
     */
    public static function logUserUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_user', $userId);
    }

    /**
     * Log user deletion
     */
    public static function logUserDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_user', $userId);
    }

    /**
     * Log employee creation
     */
    public static function logEmployeeCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_employee', $userId);
    }

    /**
     * Log employee update
     */
    public static function logEmployeeUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_employee', $userId);
    }

    /**
     * Log employee deletion
     */
    public static function logEmployeeDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_employee', $userId);
    }

    /**
     * Log settings update
     */
    public static function logSettingsUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_settings', $userId);
    }

    /**
     * Log file upload
     */
    public static function logFileUpload(?int $userId = null): ?ActivityLog
    {
        return self::log('upload_file', $userId);
    }

    /**
     * Log file deletion
     */
    public static function logFileDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_file', $userId);
    }

    /**
     * Log password change
     */
    public static function logPasswordChanged(?int $userId = null): ?ActivityLog
    {
        return self::log('change_password', $userId);
    }

    /**
     * Log permission change
     */
    public static function logPermissionChanged(?int $userId = null): ?ActivityLog
    {
        return self::log('change_permission', $userId);
    }

    /**
     * Log role assignment
     */
    public static function logRoleAssigned(?int $userId = null): ?ActivityLog
    {
        return self::log('assign_role', $userId);
    }

    // ========== ATTENDANCE ACTIONS ==========
    
    /**
     * Log clock in
     */
    public static function logClockIn(?int $userId = null): ?ActivityLog
    {
        return self::log('clock_in', $userId);
    }

    /**
     * Log clock out
     */
    public static function logClockOut(?int $userId = null): ?ActivityLog
    {
        return self::log('clock_out', $userId);
    }

    /**
     * Log attendance record created
     */
    public static function logAttendanceCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_attendance', $userId);
    }

    /**
     * Log attendance record updated
     */
    public static function logAttendanceUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_attendance', $userId);
    }

    /**
     * Log attendance record deleted
     */
    public static function logAttendanceDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_attendance', $userId);
    }

    // ========== LEAVE ACTIONS ==========
    
    /**
     * Log leave application created
     */
    public static function logLeaveCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_leave', $userId);
    }

    /**
     * Log leave application updated
     */
    public static function logLeaveUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_leave', $userId);
    }

    /**
     * Log leave application approved
     */
    public static function logLeaveApproved(?int $userId = null): ?ActivityLog
    {
        return self::log('approve_leave', $userId);
    }

    /**
     * Log leave application rejected
     */
    public static function logLeaveRejected(?int $userId = null): ?ActivityLog
    {
        return self::log('reject_leave', $userId);
    }

    /**
     * Log leave application cancelled
     */
    public static function logLeaveCancelled(?int $userId = null): ?ActivityLog
    {
        return self::log('cancel_leave', $userId);
    }

    // ========== HR ACTIONS ==========
    
    /**
     * Log employee transfer
     */
    public static function logEmployeeTransfer(?int $userId = null): ?ActivityLog
    {
        return self::log('transfer_employee', $userId);
    }

    /**
     * Log employee promotion
     */
    public static function logEmployeePromotion(?int $userId = null): ?ActivityLog
    {
        return self::log('promote_employee', $userId);
    }

    /**
     * Log employee resignation
     */
    public static function logEmployeeResignation(?int $userId = null): ?ActivityLog
    {
        return self::log('resignation', $userId);
    }

    /**
     * Log employee termination
     */
    public static function logEmployeeTermination(?int $userId = null): ?ActivityLog
    {
        return self::log('termination', $userId);
    }

    // ========== RECRUITMENT ACTIONS ==========
    
    /**
     * Log job posting created
     */
    public static function logJobPostingCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_job_posting', $userId);
    }

    /**
     * Log job posting updated
     */
    public static function logJobPostingUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_job_posting', $userId);
    }

    /**
     * Log job posting deleted
     */
    public static function logJobPostingDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_job_posting', $userId);
    }

    /**
     * Log candidate created
     */
    public static function logCandidateCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_candidate', $userId);
    }

    /**
     * Log candidate updated
     */
    public static function logCandidateUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_candidate', $userId);
    }

    /**
     * Log interview scheduled
     */
    public static function logInterviewScheduled(?int $userId = null): ?ActivityLog
    {
        return self::log('schedule_interview', $userId);
    }

    /**
     * Log offer created
     */
    public static function logOfferCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_offer', $userId);
    }

    // ========== MEETING ACTIONS ==========
    
    /**
     * Log meeting created
     */
    public static function logMeetingCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_meeting', $userId);
    }

    /**
     * Log meeting updated
     */
    public static function logMeetingUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_meeting', $userId);
    }

    /**
     * Log meeting cancelled
     */
    public static function logMeetingCancelled(?int $userId = null): ?ActivityLog
    {
        return self::log('cancel_meeting', $userId);
    }

    /**
     * Log meeting attended
     */
    public static function logMeetingAttended(?int $userId = null): ?ActivityLog
    {
        return self::log('attend_meeting', $userId);
    }

    // ========== TRAINING ACTIONS ==========
    
    /**
     * Log training program created
     */
    public static function logTrainingCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_training', $userId);
    }

    /**
     * Log training session attended
     */
    public static function logTrainingAttended(?int $userId = null): ?ActivityLog
    {
        return self::log('attend_training', $userId);
    }

    // ========== DOCUMENT ACTIONS ==========
    
    /**
     * Log document uploaded
     */
    public static function logDocumentUploaded(?int $userId = null): ?ActivityLog
    {
        return self::log('upload_document', $userId);
    }

    /**
     * Log document deleted
     */
    public static function logDocumentDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_document', $userId);
    }

    // ========== ANNOUNCEMENT ACTIONS ==========
    
    /**
     * Log announcement created
     */
    public static function logAnnouncementCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_announcement', $userId);
    }

    /**
     * Log announcement updated
     */
    public static function logAnnouncementUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_announcement', $userId);
    }

    /**
     * Log announcement deleted
     */
    public static function logAnnouncementDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_announcement', $userId);
    }

    // ========== BRANCH & DEPARTMENT ACTIONS ==========
    
    /**
     * Log branch created
     */
    public static function logBranchCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_branch', $userId);
    }

    /**
     * Log branch updated
     */
    public static function logBranchUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_branch', $userId);
    }

    /**
     * Log branch deleted
     */
    public static function logBranchDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_branch', $userId);
    }

    /**
     * Log department created
     */
    public static function logDepartmentCreated(?int $userId = null): ?ActivityLog
    {
        return self::log('create_department', $userId);
    }

    /**
     * Log department updated
     */
    public static function logDepartmentUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_department', $userId);
    }

    /**
     * Log department deleted
     */
    public static function logDepartmentDeleted(?int $userId = null): ?ActivityLog
    {
        return self::log('delete_department', $userId);
    }

    // ========== PROFILE ACTIONS ==========
    
    /**
     * Log profile updated
     */
    public static function logProfileUpdated(?int $userId = null): ?ActivityLog
    {
        return self::log('update_profile', $userId);
    }
}
