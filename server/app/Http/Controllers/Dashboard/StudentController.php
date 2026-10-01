<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $classFilter = (string) $request->query('class', '');
        $trashed = $request->boolean('trashed');

        $students = Student::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($classFilter !== '', fn ($query) => $query->where('class', $classFilter))
            ->orderBy('class')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $classes = Student::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->select('class')
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return view('dashboard.students.index', [
            'students' => $students,
            'classes' => $classes,
            'search' => $search,
            'classFilter' => $classFilter,
            'trashed' => $trashed,
            'stats' => [
                'total' => Student::query()->count(),
                'with_pin' => Student::query()->whereNotNull('pin_set_at')->count(),
                'without_pin' => Student::query()->whereNull('pin_set_at')->count(),
                'inactive' => Student::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('dashboard.students.form', ['student' => new Student]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $student = Student::query()->create($data);

        Audit::log(
            action: 'student_created',
            entityType: Student::class,
            entityId: $student->id,
            metadata: ['nisn' => $student->nisn],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('students.index')->with('status', "Siswa {$student->name} berhasil ditambahkan.");
    }

    public function edit(Student $student): View
    {
        return view('dashboard.students.form', ['student' => $student]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request, $student);

        $student->update($data);

        Audit::log(
            action: 'student_updated',
            entityType: Student::class,
            entityId: $student->id,
            metadata: ['nisn' => $student->nisn],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('students.index')->with('status', "Data {$student->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        Audit::log(
            action: 'student_deleted',
            entityType: Student::class,
            entityId: $student->id,
            metadata: ['nisn' => $student->nisn, 'name' => $student->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        $student->delete();

        return redirect()->route('students.index')->with('status', "Siswa {$student->name} berhasil dihapus (soft delete). Riwayat sesi tetap tersimpan.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $this->validatedIds($request);

        $students = Student::query()->whereKey($data['ids'])->get();

        if ($students->isEmpty()) {
            return redirect()->route('students.index')->with('status', 'Tidak ada siswa yang cocok untuk dihapus.');
        }

        foreach ($students as $student) {
            $student->delete();
        }

        Audit::log(
            action: 'students_bulk_deleted',
            entityType: Student::class,
            metadata: [
                'ids' => $students->pluck('id')->all(),
                'count' => $students->count(),
                'nisns' => $students->pluck('nisn')->all(),
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('students.index')->with(
            'status',
            "{$students->count()} siswa berhasil dihapus (soft delete). Riwayat sesi tetap tersimpan."
        );
    }

    public function restore(Request $request, int $student): RedirectResponse
    {
        $student = Student::onlyTrashed()->findOrFail($student);
        $student->restore();

        Audit::log(
            action: 'student_restored',
            entityType: Student::class,
            entityId: $student->id,
            metadata: ['nisn' => $student->nisn, 'name' => $student->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('students.index', ['trashed' => 1])
            ->with('status', "Siswa {$student->name} berhasil dipulihkan.");
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $data = $this->validatedIds($request);

        $students = Student::onlyTrashed()->whereKey($data['ids'])->get();

        if ($students->isEmpty()) {
            return redirect()->route('students.index', ['trashed' => 1])->with('status', 'Tidak ada siswa yang cocok untuk dipulihkan.');
        }

        foreach ($students as $student) {
            $student->restore();
        }

        Audit::log(
            action: 'students_bulk_restored',
            entityType: Student::class,
            metadata: [
                'ids' => $students->pluck('id')->all(),
                'count' => $students->count(),
                'nisns' => $students->pluck('nisn')->all(),
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('students.index', ['trashed' => 1])
            ->with('status', "{$students->count()} siswa berhasil dipulihkan.");
    }

    public function resetPin(Request $request, Student $student): RedirectResponse
    {
        $student->forceFill([
            'pin_algo' => null,
            'pin_salt' => null,
            'pin_iterations' => null,
            'pin_hash' => null,
            'pin_set_at' => null,
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();

        Audit::log(
            action: 'pin_reset',
            entityType: Student::class,
            entityId: $student->id,
            metadata: ['nisn' => $student->nisn],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->back()->with('status', "PIN {$student->name} berhasil direset. Siswa akan diminta membuat PIN baru saat login.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Student $student = null): array
    {
        $data = $request->validate([
            'nisn' => [
                'required', 'string', 'digits:10',
                Rule::unique('students', 'nisn')->ignore($student?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'class' => ['required', 'string', 'max:50'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedIds(Request $request): array
    {
        return $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);
    }
}
