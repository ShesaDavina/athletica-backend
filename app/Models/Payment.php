<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'payment_id';
    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'booking_id',
        'user_membership_id',
        'amount',
        'payment_method',
        'status',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'string',
        'payment_date' => 'datetime:H:i',
    ];

    // realtion
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function userMembership()
    {
        return $this->belongsTo(UserMembership::class, 'user_membership_id', 'user_membership_id');
    }

    // helper
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function markAsPaid($paymentMethod = null)
    {
        $this->status = 'paid';
        $this->payment_date = now();
        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }
        $this->save();

        if ($this->booking_id) {
            $this->booking->status = 'booked';
            $this->booking->save();
        }

        if ($this->user_membership_id) {
            $this->userMembership->status = 'active';
            $this->userMembership->save();
        }
    }

    public function markAsFailed()
    {
        $this->status = 'failed';
        $this->save();

        if ($this->booking_id) {
            $this->booking->status = 'canceled';
            $this->booking->save();
        }

        if ($this->user_membership_id) {
            $this->userMembership->status = 'expired';
            $this->userMembership->save();
        }
    }
}
