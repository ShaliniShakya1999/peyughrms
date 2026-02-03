<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = 'attendance_records';
        if (!Schema::hasColumn($tableName, 'ip_address')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('ip_address')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('attendance_records', 'ip_address')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn('ip_address');
            });
        }
    }
};
