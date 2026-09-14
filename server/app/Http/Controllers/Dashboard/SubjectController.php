<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $subjects = Subject::query()
            ->withCount('sessions')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('dashboard.subjects.index', [
            'subjects' => $subjects,
            'search' => $search,
            'editing' => $request->filled('edit') ? Subject::query()->find($request->query('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $subject = Subject::query()->create($data + ['is_active' => true]);

        Audit::log(
            action: 'subject_created',
            entityType: Subject::class,
            entityId: $subject->id,
            metadata: ['code' => $subject->code],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('subjects.index')->with('status', "Mata pelajaran {$subject->name} berhasil ditambahkan.");
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($subject->id)],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $subject->update($data);

        Audit::log(
            action: 'subject_updated',
            entityType: Subject::class,
            entityId: $subject->id,
            metadata: ['code' => $subject->code],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('subjects.index')->with('status', "Mata pelajaran {$subject->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        if ($subject->sessions()->exists()) {
            $subject->update(['is_active' => false]);

            return redirect()->route('subjects.index')->with('status', "{$subject->name} dinonaktifkan karena sudah dipakai pada sesi penggunaan.");
        }

        Audit::log(
            action: 'subject_deleted',
            entityType: Subject::class,
            entityId: $subject->id,
            metadata: ['code' => $subject->code],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        $subject->delete();

        return redirect()->route('subjects.index')->with('status', "Mata pelajaran {$subject->name} berhasil dihapus.");
    }
}
