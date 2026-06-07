<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserMembership extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'user_membership_id';
    protected $table = 'user_memberships';

    protected $fillable = [
        'user_id',
        'membership_id',
        'start_date',
        'end_date',
        'remaining_class',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'remaining_class' => 'integer',
        'status' => 'string',
    ];

    // relation
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_id', 'membership_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'user_membership_id', 'user_membership_id');
    }

    // helper
    public function isActive()
    {
        return $this->status === 'active' && now()->lessThanOrEqualTo($this->end_date);
    }

    public function hasRemainingClass()
    {
        if ($this->membership->isUnlimited()) {
            return true;
        }
        return $this->remaining_class > 0;
    }

    public function useClass()
    {
        if (!$this->membership->isUnlimited() && $this->remaining_class > 0) {
            $this->remaining_class--;
            $this->save();
        }
    }
}
