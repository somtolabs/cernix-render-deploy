<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    public const PURPOSE_VERIFICATION_SELFIE = 'verification_selfie';
    public const PURPOSE_ID_CARD = 'id_card';
    public const PURPOSE_PROFILE_PHOTO = 'profile_photo';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'media';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'purpose',
        'disk',
        'storage_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'status',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo(null, 'owner_type', 'owner_id');
    }

    public function isPrivate(): bool
    {
        return $this->disk === 's3_private';
    }
}
