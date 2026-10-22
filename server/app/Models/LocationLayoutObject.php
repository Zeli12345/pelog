<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationLayoutObject extends Model
{
    protected $fillable = [
        'location_id',
        'type',
        'device_id',
        'label',
        'x',
        'y',
        'w',
        'h',
        'rotation',
        'color',
        'z_index',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'w' => 'float',
            'h' => 'float',
            'rotation' => 'integer',
            'z_index' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
