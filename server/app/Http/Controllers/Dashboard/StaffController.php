<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\StaffMember;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $staff = StaffMember::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('nip_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.staff.index', [
            'staff' => $staff,
            'search' => $search,
            'stats' => [
                'total' => StaffMember::query()->count(),
                'teachers' => StaffMember::query()->where('role', 'teacher')->count(),
                'inactive' => StaffMember::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('dashboard.staff.form', ['member' => new StaffMember]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = StaffMember::query()->create($this->validated($request));

        Audit::log(
            action: 'staff_created',
            entityType: StaffMember::class,
            entityId: $member->id,
            metadata: ['nip_id' => $member->nip_id],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('staff.index')->with('status', "{$member->name} berhasil ditambahkan.");
    }

    public function edit(StaffMember $staff): View
    {
        return view('dashboard.staff.form', ['member' => $staff]);
    }

    public function update(Request $request, StaffMember $staff): RedirectResponse
    {
        $staff->update($this->validated($request, $staff));

        Audit::log(
            action: 'staff_updated',
            entityType: StaffMember::class,
            entityId: $staff->id,
            metadata: ['nip_id' => $staff->nip_id],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('staff.index')->with('status', "Data {$staff->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, StaffMember $staff): RedirectResponse
    {
        Audit::log(
            action: 'staff_deleted',
            entityType: StaffMember::class,
            entityId: $staff->id,
            metadata: ['nip_id' => $staff->nip_id, 'name' => $staff->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        $staff->delete();

        return redirect()->route('staff.index')->with('status', "{$staff->name} berhasil dihapus (soft delete).");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?StaffMember $member = null): array
    {
        $data = $request->validate([
            'nip_id' => [
                'required', 'string', 'max:30',
                Rule::unique('staff_members', 'nip_id')->ignore($member?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', 'in:teacher,staff,admin'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
