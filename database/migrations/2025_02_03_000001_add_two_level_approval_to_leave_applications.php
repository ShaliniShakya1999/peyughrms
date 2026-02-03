<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Two-level leave approval: Manager/HR first approve → Admin final approve.
     */
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->foreignId('first_approved_by')->nullable()->after('manager_comments')->constrained('users')->onDelete('set null');
            $table->timestamp('first_approved_at')->nullable()->after('first_approved_by');
        });

        // Add 'manager_approved' to status enum (MySQL)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE leave_applications MODIFY COLUMN status ENUM('pending', 'manager_approved', 'approved', 'rejected') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE leave_applications DROP CONSTRAINT IF EXISTS leave_applications_status_check");
            DB::statement("ALTER TABLE leave_applications ADD CONSTRAINT leave_applications_status_check CHECK (status::text = ANY (ARRAY['pending', 'manager_approved', 'approved', 'rejected']::text[]))");
        }
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropForeign(['first_approved_by']);
            $table->dropColumn(['first_approved_by', 'first_approved_at']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE leave_applications MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE leave_applications DROP CONSTRAINT IF EXISTS leave_applications_status_check");
            DB::statement("ALTER TABLE leave_applications ADD CONSTRAINT leave_applications_status_check CHECK (status::text = ANY (ARRAY['pending', 'approved', 'rejected']::text[]))");
        }
    }
};
