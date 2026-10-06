<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreUserRequest;
use App\Http\Requests\Dashboard\UpdateUserRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\UserRoleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));
        $roleFilter = (string) $request->query('role', '');
        $statusFilter = (string) $request->query('status', '');

        $adminRoles = [UserRole::AdminUtama->value, UserRole::SubAdmin->value];

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter === 'admin', fn ($query) => $query->whereIn('role', $adminRoles))
            ->when($roleFilter === 'viewer', fn ($query) => $query->where('role', UserRole::Viewer->value))
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.users.index', [
            'users' => $users,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'stats' => [
                'total' => User::query()->count(),
                'admins' => User::query()->whereIn('role', $adminRoles)->count(),
                'viewers' => User::query()->where('role', UserRole::Viewer->value)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('dashboard.users.form', [
            'user' => new User(['role' => UserRole::Viewer]),
            'roles' => UserRoleOptions::assignableBy($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'email_verified_at' => now(),
        ]);

        Audit::log(
            action: 'user_created',
            entityType: User::class,
            entityId: $user->id,
            metadata: ['email' => $user->email, 'role' => $user->role->value],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('users.index')->with('status', "Pengguna {$user->name} berhasil ditambahkan.");
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('dashboard.users.form', [
            'user' => $user,
            'roles' => UserRoleOptions::assignableBy($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        Audit::log(
            action: 'user_updated',
            entityType: User::class,
            entityId: $user->id,
            metadata: ['email' => $user->email, 'role' => $user->role->value, 'is_active' => $user->is_active],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('users.index')->with('status', "Pengguna {$user->name} berhasil diperbarui.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $user->is_active = ! $user->is_active;
        $user->save();

        Audit::log(
            action: $user->is_active ? 'user_activated' : 'user_deactivated',
            entityType: User::class,
            entityId: $user->id,
            metadata: ['email' => $user->email],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', $user->is_active
            ? "Akun {$user->name} diaktifkan kembali."
            : "Akun {$user->name} dinonaktifkan (tidak bisa login).");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $name = $user->name;
        $email = $user->email;

        $user->delete();

        Audit::log(
            action: 'user_deleted',
            entityType: User::class,
            entityId: $user->id,
            metadata: ['email' => $email],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('users.index')->with('status', "Pengguna {$name} dihapus.");
    }
}
