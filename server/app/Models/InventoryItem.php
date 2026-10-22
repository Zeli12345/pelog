<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'code',
        'category_id',
        'unit',
        'quantity',
        'min_quantity',
        'location_id',
        'position_detail',
        'condition',
        'attributes',
        'notes',
        'photo_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_quantity' => 'integer',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity;
    }
}
