<?php

namespace App\Http\Controllers\Admin;

use App\Data\OfficeOptionData;
use App\Data\UserRowData;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('office')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): UserRowData => UserRowData::fromUser($user, $request->user()))
            ->all();

        $offices = Office::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData($office->id, $office->name, $office->city))
            ->all();

        return Inertia::render('admin/Users', [
            'users' => $users,
            'offices' => $offices,
            'roles' => collect(Role::cases())->map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->all(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'office_id' => $validated['office_id'] ?? null,
            'active' => $validated['active'] ?? true,
            'password' => $validated['password'],
        ]);

        $user->markEmailAsVerified();

        return to_route('users.index', [], 303)->with('success', __('User created.'));
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();
        $actor = $request->user();

        if ($actor !== null && $actor->is($user) && ! $validated['active']) {
            throw ValidationException::withMessages([
                'active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $nextRole = Role::from($validated['role']);

        if ($user->role === Role::Owner
            && $user->active
            && ($nextRole !== Role::Owner || ! $validated['active'])
            && ! User::query()
                ->whereKeyNot($user->id)
                ->where('role', Role::Owner)
                ->where('active', true)
                ->exists()) {
            throw ValidationException::withMessages([
                'role' => __('At least one active owner is required.'),
            ]);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $nextRole,
            'office_id' => $validated['office_id'] ?? null,
            'active' => $validated['active'],
        ]);

        return to_route('users.index', [], 303)->with('success', __('User updated.'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        return $status === Password::RESET_LINK_SENT
            ? to_route('users.index', [], 303)->with('success', __('Password reset link sent.'))
            : to_route('users.index', [], 303)->with('error', __($status));
    }

    public function setPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('setPassword', $user);

        $validated = $request->validate([
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ]);

        $user->forceFill(['password' => $validated['password']])->save();

        return to_route('users.index', [], 303)->with('success', __('Password updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        abort_if($this->actor($request)->is($user), 403, __('You cannot delete your own account.'));

        if ($user->role === Role::Owner
            && $user->active
            && ! User::query()
                ->whereKeyNot($user->id)
                ->where('role', Role::Owner)
                ->where('active', true)
                ->exists()) {
            return to_route('users.index', [], 303)->with('error', __('At least one active owner is required.'));
        }

        try {
            $user->delete();
            // The database refuses to delete a user that clients or postponements still point to.
            // @phpstan-ignore catch.neverThrown
        } catch (QueryException) {
            return to_route('users.index', [], 303)->with(
                'error',
                __('This user is linked to clients or other records and cannot be deleted. Deactivate the account instead.'),
            );
        }

        return to_route('users.index', [], 303)->with('success', __('User deleted.'));
    }
}
