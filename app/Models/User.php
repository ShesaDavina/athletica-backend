<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // JWT required methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'user_id' => $this->user_id,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }

    // relation
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'trainer_id', 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'user_id', 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_id', 'user_id');
    }

    public function userMemberships()
    {
        return $this->hasMany(UserMembership::class, 'user_id', 'user_id');
    }

    public function activeMembership()
    {
        return $this->hasOne(UserMembership::class, 'user_id', 'user_id')->where('status', 'active')->with('membership');
    }

    // helper
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTrainer()
    {
        return $this->role === 'trainer';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }
}
