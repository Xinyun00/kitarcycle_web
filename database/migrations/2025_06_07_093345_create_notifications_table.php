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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Recipient of notification
            $table->string('type'); // event_created, event_updated, event_cancelled, pickup_requested, pickup_updated
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data (event_id, pickup_id, etc.)
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_sent')->default(false); // Track if Firebase notification was sent
            $table->timestamp('sent_at')->nullable();
            $table->string('firebase_message_id')->nullable(); // Store Firebase message ID
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
