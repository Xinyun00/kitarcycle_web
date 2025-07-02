<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['user_id']);

            // Add new columns
            $table->string('recipient_type')->after('id')->default('user'); // 'user' or 'organizer'
            $table->renameColumn('user_id', 'recipient_id');

            // Add new indexes
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['recipient_type', 'recipient_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Drop new indexes
            $table->dropIndex(['recipient_type', 'recipient_id']);
            $table->dropIndex(['recipient_type', 'recipient_id', 'is_read']);

            // Revert column changes
            $table->renameColumn('recipient_id', 'user_id');
            $table->dropColumn('recipient_type');

            // Re-add the foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};