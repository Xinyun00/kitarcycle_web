<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    // Notification types constants
    const TYPE_GENERAL = 'general';
    const TYPE_EVENT_NOTIFICATION = 'event_notification';
    const TYPE_PICKUP_REQUESTED = 'pickup_requested';
    const TYPE_PICKUP_UPDATED = 'pickup_updated';
    const TYPE_SYSTEM = 'system';
    const TYPE_PROMOTIONAL = 'promotional';

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'is_sent',
        'sent_at',
        'firebase_message_id'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_sent' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the recipient of the notification
     */
    public function recipient()
    {
        return $this->morphTo();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent($firebaseMessageId = null)
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
            'firebase_message_id' => $firebaseMessageId
        ]);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for specific notification type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for recent notifications
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted created date
     */
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    /**
     * Get notification icon based on type
     */
    public function getIconAttribute()
    {
        return match($this->type) {
            self::TYPE_EVENT_NOTIFICATION => 'event',
            self::TYPE_PICKUP_REQUESTED => 'pickup',
            self::TYPE_PICKUP_UPDATED => 'update',
            self::TYPE_SYSTEM => 'system',
            self::TYPE_PROMOTIONAL => 'promotion',
            default => 'notification'
        };
    }

    /**
     * Get notification color based on type
     */
    public function getColorAttribute()
    {
        return match($this->type) {
            self::TYPE_EVENT_NOTIFICATION => '#3B82F6', // blue
            self::TYPE_PICKUP_REQUESTED => '#F59E0B',   // amber
            self::TYPE_PICKUP_UPDATED => '#10B981',     // emerald
            self::TYPE_SYSTEM => '#EF4444',             // red
            self::TYPE_PROMOTIONAL => '#8B5CF6',        // violet
            default => '#6B7280'                        // gray
        };
    }

    /**
     * Check if notification is recent (within 24 hours)
     */
    public function getIsRecentAttribute()
    {
        return $this->created_at->gt(now()->subHours(24));
    }

    /**
     * Get all available notification types
     */
    public static function getTypes()
    {
        return [
            self::TYPE_GENERAL,
            self::TYPE_EVENT_NOTIFICATION,
            self::TYPE_PICKUP_REQUESTED,
            self::TYPE_PICKUP_UPDATED,
            self::TYPE_SYSTEM,
            self::TYPE_PROMOTIONAL
        ];
    }
}
