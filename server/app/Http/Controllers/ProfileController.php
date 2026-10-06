<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Akun Admin Utama terakhir tidak boleh terhapus dari halaman Profil:
        // tanpa akun admin, halaman Pengguna tidak bisa dikelola lagi.
        if ($user instanceof User && $user->isAdminUtama()) {
            $hasOtherActiveAdmin = User::query()
                ->where('role', UserRole::AdminUtama->value)
                ->where('is_active', true)
                ->whereKeyNot($user->getKey())
                ->exists();

            if (! $hasOtherActiveAdmin) {
                return Redirect::back()->withErrors([
                    'password' => 'Akun admin utama terakhir tidak dapat dihapus.',
                ], 'userDeletion');
            }
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
