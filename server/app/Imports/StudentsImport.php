<?php

namespace App\Imports;

use App\Models\Student;
use DateTimeInterface;
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
            $birthDateRaw = $row['tanggal_lahir'] ?? $row['birth_date'] ?? null;
            $birthDate = $this->parseBirthDate($birthDateRaw);

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

            if ($this->isEmptyBirthDate($birthDateRaw)) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Tanggal lahir wajib diisi.',
                ];

                continue;
            }

            if ($birthDate === null) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Tanggal lahir tidak valid. Gunakan format Y-m-d atau d/m/Y.',
                ];

                continue;
            }

            if (mb_strlen($name) > 150) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Nama maksimal 150 karakter.',
                ];

                continue;
            }

            if (mb_strlen($class) > 50) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Kelas maksimal 50 karakter.',
                ];

                continue;
            }

            $student = Student::withTrashed()->firstOrNew(['nisn' => $nisn]);
            $isNew = ! $student->exists;

            $student->name = $name;
            $student->class = $class;
            $student->birth_date = $birthDate;

            if ($isNew) {
                $student->is_active = true;
            }

            try {
                $student->save();

                if ($student->trashed()) {
                    $student->restore();
                }
            } catch (\Throwable) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'nisn' => $nisn,
                    'message' => 'Baris gagal disimpan.',
                ];

                continue;
            }

            if ($isNew) {
                $this->created++;
            } else {
                $this->updated++;
            }
        }
    }

    /**
     * Terima Y-m-d (2008-07-14) atau d/m/Y (14/07/2008). Sel Excel yang
     * terbaca sebagai tanggal juga diterima.
     */
    private function parseBirthDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function isEmptyBirthDate(mixed $value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return false;
        }

        return trim((string) $value) === '';
    }
}
