<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'schedule_id';
    protected $table = 'schedules';

    protected $fillable = [
        'trainer_id',
        'class_id',
        'schedule_date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'schedule_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    // relation
    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id', 'user_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id', 'class_id');
    }


    public function bookings()
    {
        return $this->hasMany(Booking::class, 'schedule_id', 'schedule_id');
    }

    // helper
    public function getRegisteredCountAttribute()
    {
        return $this->bookings()
            ->where('status', '!=', 'canceled')
            ->count();
    }

    public function isAvailable()
    {
        return $this->getRegisteredCountAttribute() < $this->classModel->capacity;
    }

    public function getFormattedStartTimeAttribute()
    {
        return \Carbon\Carbon::parse($this->start_time)->format('H:i');
    }

    public function getFormattedEndTimeAttribute()
    {
        return \Carbon\Carbon::parse($this->end_time)->format('H:i');
    }
}
