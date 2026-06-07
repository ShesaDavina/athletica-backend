<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'membership_id';
    protected $table = 'memberships';

    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'class_limit',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'class_limit' => 'integer',
    ];

    // relation
    public function userMemberships()
    {
        return $this->hasMany(UserMembership::class, 'membership_id', 'membership_id');
    }

    // helper
    // membership unlimited?
    public function isUnlimited()
    {
        return is_null($this->class_limit);
    }
}
