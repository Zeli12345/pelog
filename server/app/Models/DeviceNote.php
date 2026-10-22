<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'device_id',
        'usage_session_id',
        'category',
        'title',
        'body',
        'status',
        'priority',
        'occurred_at',
        'resolved_at',
        'cost',
        'vendor',
        'tags',
        'pinned',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'cost' => 'decimal:2',
            'tags' => 'array',
            'pinned' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(UsageSession::class, 'usage_session_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoteAttachment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }
}
