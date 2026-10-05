<?php

namespace App\Models;

use App\Enums\CloseReason;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
        return $this->hasOne(Screenshot::class)->latestOfMany('id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class)->orderBy('captured_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * Filter rentang tanggal dalam zona waktu sekolah (WITA) — kolom waktu
     * disimpan UTC, sehingga tanggal "06 Oktober" berarti 06 Okt 00:00 WITA
     * s.d. 23:59 WITA, dan sesi hasil sinkron offline (started_at_server NULL)
     * tetap ikut terhitung memakai started_at_client.
     */
    public function scopeFilterByDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        $column = 'COALESCE(usage_sessions.started_at_server, usage_sessions.started_at_client, usage_sessions.created_at)';

        if ($from !== null && $from !== '') {
            $query->whereRaw($column.' >= ?', [Carbon::parse($from, 'Asia/Makassar')->startOfDay()->utc()]);
        }

        if ($to !== null && $to !== '') {
            $query->whereRaw($column.' <= ?', [Carbon::parse($to, 'Asia/Makassar')->endOfDay()->utc()]);
        }

        return $query;
    }

    public function isActive(): bool
    {
        return $this->closed_at === null;
    }
}
