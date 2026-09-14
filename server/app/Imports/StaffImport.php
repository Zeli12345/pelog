<?php

namespace App\Imports;

use App\Models\StaffMember;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StaffImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, nip: string, message: string}> */
    public array $errors = [];

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;

            $nip = trim((string) ($row['nip'] ?? $row['nip_id'] ?? ''));
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
            $role = strtolower(trim((string) ($row['peran'] ?? $row['role'] ?? 'teacher')));

            if ($nip === '' && $name === '') {
                $this->skipped++;

                continue;
            }

            if ($nip === '') {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nip' => '-',
                    'message' => 'NIP/NUPTK wajib diisi.',
                ];

                continue;
            }

            if ($name === '') {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nip' => $nip,
                    'message' => 'Nama wajib diisi.',
                ];

                continue;
            }

            if (! in_array($role, ['teacher', 'staff', 'admin'], true)) {
                $role = 'teacher';
            }

            $member = StaffMember::withTrashed()->firstOrNew(['nip_id' => $nip]);
            $isNew = ! $member->exists;

            $member->name = $name;
            $member->role = $role;

            if ($isNew) {
                $member->is_active = true;
            }

            $member->save();

            if ($member->trashed()) {
                $member->restore();
            }

            if ($isNew) {
                $this->created++;
            } else {
                $this->updated++;
            }
        }
    }
}
