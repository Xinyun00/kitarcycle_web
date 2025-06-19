<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserFcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'notifiable_id',   // Changed from user_id
        'notifiable_type',
        'token',
        'device_type',
        'device_id',
        'device_name',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('notifiable_id', $userId)
            ->where('notifiable_type', \App\Models\User::class); // Use the full class name
    }

    public function updateLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }
}
