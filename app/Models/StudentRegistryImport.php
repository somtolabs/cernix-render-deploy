<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRegistryImport extends Model
{
    protected $fillable = [
        'uploaded_by',
        'original_filename',
        'total_rows',
        'imported_rows',
        'skipped_rows',
        'failed_rows',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'error_summary' => 'array',
        ];
    }
}
