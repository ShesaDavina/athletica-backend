<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'class_id';
    protected $table = 'classes';

    protected $fillable = [
        'class_name',
        'description',
        'price',
        'capacity',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'capacity' => 'integer'
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    // relation
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id', 'class_id');
    }
}
