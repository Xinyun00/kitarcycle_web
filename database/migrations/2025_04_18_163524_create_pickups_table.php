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
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->string('images');
            $table->string('category');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('schedule_id'); // NEW: driver_schedule slot
            $table->text('address');
            $table->decimal('estimated_weight', 8, 2)->nullable(); // User's input
            $table->decimal('actual_weight', 8, 2)->nullable();
            $table->enum('status', ['Pending', 'In Progress', 'Completed', 'Cancelled'])->default('Pending'); // Status of the pickup
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('organizer_id')->references('id')->on('organizers')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
            $table->foreign('schedule_id')->references('id')->on('driver_schedules')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickups');
    }
};
