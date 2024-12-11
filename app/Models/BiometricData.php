<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiometricData extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'machine_number',
        'fingerprint_index',
        'fingerprint_data',
    ];
}
