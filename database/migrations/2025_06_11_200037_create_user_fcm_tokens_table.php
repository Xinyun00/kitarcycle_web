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
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key

            // Polymorphic relationship columns
            $table->unsignedBigInteger('notifiable_id'); // ID of the related model (e.g., User ID)
            $table->string('notifiable_type');         // Class name of the related model (e.g., 'App\Models\User')

            $table->string('token')->unique(); // The FCM token, should be unique for a given notifiable entity
            $table->string('device_type')->nullable(); // e.g., 'android', 'ios', 'web'
            $table->string('device_id')->nullable();   // Unique device identifier
            $table->string('device_name')->nullable(); // Human-readable device name (e.g., 'John's iPhone 15')

            $table->boolean('is_active')->default(true); // Whether the token is currently active for notifications
            $table->timestamp('last_used_at')->useCurrent(); // Timestamp of the last time the token was used

            $table->timestamps(); // Adds created_at and updated_at columns

            // Add indexes for efficient lookups on the polymorphic columns
            $table->index(['notifiable_id', 'notifiable_type']);
            // Add a unique constraint for the polymorphic relationship and token
            $table->unique(['notifiable_id', 'notifiable_type', 'token'], 'user_fcm_tokens_notifiable_token_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
