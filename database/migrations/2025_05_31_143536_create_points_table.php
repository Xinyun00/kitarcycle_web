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
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pickup_id');
            $table->decimal('points_earned', 8, 2);   // Calculated total points
            $table->decimal('actual_weight', 8, 2);   // Actual weight from pickup
            $table->decimal('points_per_kg', 8, 2);   // Category rate used at time
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pickup_id')->references('id')->on('pickups')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points');
    }
};
