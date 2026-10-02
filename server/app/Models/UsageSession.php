<?php

namespace App\Models;

use App\Enums\CloseReason;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UsageSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_uuid',
        'device_id',
        'user_type',
        'student_id',
        'staff_id',
        'subject_id',
        'usage_purpose',
        'started_at_client',
        'started_at_server',
        'last_heartbeat_at',
        'closed_at',
        'close_reason',
        'duration_minutes',
        'sync_source',
    ];

    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'close_reason' => CloseReason::class,
            'started_at_client' => 'datetime',
            'started_at_server' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'closed_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function screenshot(): HasOne
    {
        return $this->hasOne(Screenshot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isActive(): bool
    {
        return $this->closed_at === null;
    }
}
