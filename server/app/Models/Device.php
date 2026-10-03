<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'hostname',
        'label',
        'device_token_hash',
        'mac_list',
        'device_type',
        'location_label',
        'status',
        'storage_total_gb',
        'storage_used_gb',
        'agent_version',
        'windows_version',
        'last_seen_at',
        'screenshot_requested_at',
        'enrolled_at',
        'is_active',
    ];

    protected $hidden = [
        'device_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'mac_list' => 'array',
            'status' => DeviceStatus::class,
            'storage_total_gb' => 'integer',
            'storage_used_gb' => 'integer',
            'last_seen_at' => 'datetime',
            'screenshot_requested_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UsageSession::class);
    }

    public function activeSession(): ?UsageSession
    {
        return $this->sessions()->active()->latest('started_at_server')->first();
    }
}
