<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, nisn: string, message: string}> */
    public array $errors = [];

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;

            $nisn = (string) preg_replace('/\D/', '', (string) ($row['nisn'] ?? ''));
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
            $class = trim((string) ($row['kelas'] ?? $row['class'] ?? ''));

            if ($nisn === '' && $name === '' && $class === '') {
                $this->skipped++;

                continue;
            }

            if (! preg_match('/^\d{10}$/', $nisn)) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'NISN harus 10 angka.',
                ];

                continue;
            }

            if ($name === '') {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Nama wajib diisi.',
                ];

                continue;
            }

            if ($class === '') {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Kelas wajib diisi.',
                ];

                continue;
            }

            $student = Student::withTrashed()->firstOrNew(['nisn' => $nisn]);
            $isNew = ! $student->exists;

            $student->name = $name;
            $student->class = $class;

            if ($isNew) {
                $student->is_active = true;
            }

            $student->save();

            if ($student->trashed()) {
                $student->restore();
            }

            if ($isNew) {
                $this->created++;
            } else {
                $this->updated++;
            }
        }
    }
}
