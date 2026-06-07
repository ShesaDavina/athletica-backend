<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'booking_id';
    protected $table = 'bookings';

    protected $fillable = [
        'user_id',
        'schedule_id',
        'booking_type',
        'status',
    ];

    protected $casts = [
        'booking_type' => 'string',
        'status' => 'string',
    ];

    // relation
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'booking_id', 'booking_id');
    }

    // helper
    public function isPaid()
    {
        return $this->payment && $this->payment->status === 'paid';
    }

    public function canCancel()
    {
        return $this->status === 'booked' && $this->schedule->schedule_date > now();
    }

    public function cancel()
    {
        if ($this->canCancel()) {
            $this->status = 'canceled';
            $this->save();

            if ($this->booking_type === 'membership') {
                $userMembership = UserMembership::where('user_id', $this->user_id)
                    ->where('status', 'active')
                    ->first();
                if ($userMembership) {
                    $userMembership->remaining_class++;
                    $userMembership->save();
                }
            }
        }
    }
}
