<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $labels = DB::table('devices')
            ->whereNotNull('location_label')
            ->where('location_label', '!=', '')
            ->pluck('location_label')
            ->unique(fn ($label) => $this->normalize((string) $label));

        $map = [];

        foreach ($labels as $label) {
            $normalized = $this->normalize((string) $label);

            if ($normalized === '') {
                continue;
            }

            $id = DB::table('locations')->where('normalized_name', $normalized)->value('id');

            if ($id === null) {
                $id = DB::table('locations')->insertGetId([
                    'name' => trim((string) $label),
                    'normalized_name' => $normalized,
                    'is_active' => true,
                    'sort_order' => 0,
                    'layout_version' => 1,
                    'canvas_width' => 1000,
                    'canvas_height' => 600,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $map[$normalized] = $id;
        }

        DB::table('devices')
            ->whereNotNull('location_label')
            ->orderBy('id')
            ->each(function ($device) use ($map) {
                $normalized = $this->normalize((string) $device->location_label);

                if (isset($map[$normalized])) {
                    DB::table('devices')->where('id', $device->id)->update([
                        'location_id' => $map[$normalized],
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('devices')->update(['location_id' => null]);
        DB::table('locations')->delete();
    }

    private function normalize(string $name): string
    {
        $normalized = Str::lower(trim($name));
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
};
