<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockSisRecord extends Model
{
    protected $table = 'mock_sis';

    protected $primaryKey = 'matric_no';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'matric_no',
        'full_name',
        'department',
        'department_code',
        'faculty_code',
        'level',
        'photo_path',
    ];
}
