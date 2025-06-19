<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify enum column 'status' to add 'Rejected'
        DB::statement("ALTER TABLE pickups MODIFY COLUMN status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled', 'Rejected') DEFAULT 'Pending'");

        // Add 'rejection_reason' column if it doesn't exist
        if (!Schema::hasColumn('pickups', 'rejection_reason')) {
            Schema::table('pickups', function (Blueprint $table) {
                $table->string('rejection_reason')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        // Drop 'rejection_reason' if exists
        if (Schema::hasColumn('pickups', 'rejection_reason')) {
            Schema::table('pickups', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }

        // Revert enum column 'status' to remove 'Rejected'
        DB::statement("ALTER TABLE pickups MODIFY COLUMN status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending'");
    }
};
