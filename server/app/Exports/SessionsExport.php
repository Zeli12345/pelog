<?php

namespace App\Exports;

use App\Models\UsageSession;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SessionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            ->orderByDesc('started_at_server');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Tanggal', 'Perangkat', 'Tipe Pengguna', 'Nama', 'Kelas/Unit',
            'Mapel', 'Tujuan', 'Mulai', 'Selesai', 'Durasi (menit)',
            'Pemahaman', 'Refleksi', 'Status Tutup',
        ];
    }

    /**
     * @param  UsageSession  $session
     * @return array<int, mixed>
     */
    public function map($session): array
    {
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
            $session->duration_minutes,
            $session->comprehension_level?->label() ?? '-',
            $session->student_feedback ?? '-',
            $session->close_reason?->label() ?? '-',
        ];
    }
}
