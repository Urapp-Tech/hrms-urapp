<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'created_by',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'shift_id');
    }

    /**
     * Check if the shift crosses midnight.
     */
    public function crossesMidnight(): bool
    {
        return $this->start_time > $this->end_time;
    }

}
