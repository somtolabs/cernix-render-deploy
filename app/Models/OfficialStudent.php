<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialStudent extends Model
{
    protected $fillable = [
        'matric_number',
        'full_name',
        'department',
        'faculty',
        'level',
        'programme',
        'academic_session',
        'status',
    ];
}
