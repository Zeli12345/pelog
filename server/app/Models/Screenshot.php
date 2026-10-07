<?php

namespace App\Models;

use App\Enums\ScreenshotFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screenshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'screenshot_uuid',
        'content_hash',
        'usage_session_id',
        'format',
        'path',
        'thumb_path',
        'size_bytes',
        'captured_at',
        'captured_at_client',
    ];

    protected function casts(): array
    {
        return [
            'format' => ScreenshotFormat::class,
            'size_bytes' => 'integer',
            'captured_at' => 'datetime',
            'captured_at_client' => 'datetime',
        ];
    }

    public function usageSession(): BelongsTo
    {
        return $this->belongsTo(UsageSession::class);
    }
}
