<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'location_from_id',
        'location_to_id',
        'reference',
        'note',
        'actor_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function locationFrom(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_from_id');
    }

    public function locationTo(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_to_id');
    }
}
