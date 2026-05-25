<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examiner extends Model
{
    protected $table = 'examiners';

    protected $primaryKey = 'examiner_id';

    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'username',
        'password_hash',
        'role',
        'admin_user_id',
        'is_active',
        'last_active_at',
        'created_at',
    ];

    protected $hidden = [
        'password_hash',
    ];
}
