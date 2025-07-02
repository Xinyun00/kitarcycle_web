<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pickups', 'category_id')) {
            Schema::table('pickups', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('images');
                // foreign key constraints can be added here if needed
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pickups', 'category_id')) {
            Schema::table('pickups', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }
    }
};
