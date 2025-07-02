<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Organizer extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    // Keep your existing fillable
    protected $fillable = [
        'name',
        'email',
        'password',
        'contact_number',
        'location',
        'recyclingTypesAccepted',
        'api_token',
        'image'
    ];

    protected $hidden = [
        'password',
        'remember_token', // Typically added for Authenticatable models
    ];

    protected $table = 'organizers';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }

    public function fcmTokens()
    {
        return $this->morphMany(UserFcmToken::class, 'notifiable');
    }
}
