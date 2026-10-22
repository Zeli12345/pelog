<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'is_active',
        'sort_order',
        'layout_version',
        'canvas_width',
        'canvas_height',
        'background_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'layout_version' => 'integer',
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
        ];
    }

    public static function normalizeName(string $name): string
    {
        $normalized = Str::lower(trim($name));
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Mencari ruangan yang sudah ada, termasuk padanan kata utuh.
     * Contoh: "rpl" cocok dengan "Lab RPL 1" agar tidak membuat duplikat;
     * "Lab RPL 2" tidak dianggap sama dengan "Lab RPL 1".
     */
    public static function findSimilar(string $name): ?self
    {
        $normalized = self::normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        $exact = self::query()->where('normalized_name', $normalized)->first();

        if ($exact !== null) {
            return $exact;
        }

        return self::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->first(function (self $location) use ($normalized) {
                $existing = ' '.$location->normalized_name.' ';

                return str_contains($existing, ' '.$normalized.' ');
            });
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function layoutObjects(): HasMany
    {
        return $this->hasMany(LocationLayoutObject::class);
    }

    public function deviceCount(): int
    {
        return $this->devices()->count();
    }
}
