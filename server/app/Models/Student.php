<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nisn',
        'name',
        'class',
        'pin_algo',
        'pin_salt',
        'pin_iterations',
        'pin_hash',
        'pin_set_at',
        'pin_failed_attempts',
        'pin_locked_until',
        'is_active',
    ];

    protected $hidden = [
        'pin_algo',
        'pin_salt',
        'pin_iterations',
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'pin_set_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'pin_failed_attempts' => 'integer',
            'pin_iterations' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UsageSession::class);
    }

    public function hasPin(): bool
    {
        return $this->pin_set_at !== null && $this->pin_hash !== null;
    }
}
