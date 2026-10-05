<?php

namespace App\Exports;

use App\Models\UsageSession;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;

class SessionsExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function __construct(private readonly array $filters) {}

    public function query(): Builder
    {
        return UsageSession::query()
            ->with(['device', 'student', 'staff', 'subject'])
            ->when($this->filters['from'], fn ($query, $from) => $query->whereDate('started_at_server', '>=', $from))
            ->when($this->filters['to'], fn ($query, $to) => $query->whereDate('started_at_server', '<=', $to))
            ->when($this->filters['user_type'], fn ($query, $type) => $query->where('user_type', $type))
            ->when($this->filters['device_id'], fn ($query, $deviceId) => $query->where('device_id', $deviceId))
            ->when(($this->filters['q'] ?? '') !== '', function ($query) {
                $search = $this->filters['q'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('usage_purpose', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student->where('name', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        })
                        ->orWhereHas('staff', fn ($staff) => $staff->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('started_at_server');
    }

    /**
     * Force formula-like free-text values to be written as inert strings.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $cell->setValueExplicit(StringHelper::sanitizeUTF8($value), DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Tanggal', 'Perangkat', 'Tipe Pengguna', 'Nama', 'Kelas/Unit',
            'Mapel', 'Tujuan', 'Mulai', 'Selesai', 'Durasi (menit)',
            'Status Tutup',
        ];
    }

    /**
     * @param  UsageSession  $session
     * @return array<int, mixed>
     */
    public function map($session): array
    {
        $startedAt = $session->started_at_server ?? $session->started_at_client;
        $duration = $session->closed_at === null && $startedAt !== null
            ? max(0, (int) $startedAt->diffInMinutes(now()))
            : $session->duration_minutes;

        return [
            ($session->started_at_server ?? $session->started_at_client)?->timezone('Asia/Makassar')->format('Y-m-d') ?? '-',
            $session->device?->label ?? $session->device?->hostname ?? '-',
            $session->user_type->label(),
            $session->student?->name ?? $session->staff?->name ?? '-',
            $session->student?->class ?? $session->staff?->role?->label() ?? '-',
            $session->subject?->name ?? '-',
            $session->usage_purpose,
            ($session->started_at_server ?? $session->started_at_client)?->timezone('Asia/Makassar')->format('H:i') ?? '-',
            $session->closed_at?->timezone('Asia/Makassar')->format('H:i') ?? '-',
            $duration,
            $session->close_reason?->label() ?? '-',
        ];
    }
}
