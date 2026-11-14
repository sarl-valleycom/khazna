<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->isOwner() && ! User::query()
            ->whereKeyNot($user->getKey())
            ->where('role', Role::Owner)
            ->where('active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'password' => __('khazna.errors.last_owner'),
            ]);
        }

        // Delete while still signed in so the audit row records who did it.
        $user->delete();

        // Not Auth::logout(): that would try to save the remember token of the
        // row we just deleted. Drop the session user and the remember cookie.
        $guard = Auth::guard('web');
        $guard->forgetUser();
        Cookie::queue(Cookie::forget($guard->getRecallerName()));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
