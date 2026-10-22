<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteAttachment extends Model
{
    protected $fillable = [
        'device_note_id',
        'path',
        'thumb_path',
        'original_name',
        'mime',
        'size_bytes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(DeviceNote::class, 'device_note_id');
    }
}
