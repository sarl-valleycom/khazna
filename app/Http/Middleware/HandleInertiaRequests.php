<?php

namespace App\Http\Middleware;

use App\Data\AuthData;
use App\Data\FlashData;
use App\Data\OfficeOptionData;
use App\Data\SettingsData;
use App\Data\UserData;
use App\Models\Office;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $abilities = [
            'view-salaries' => $user?->can('view-salaries') ?? false,
        ];

        $userData = UserData::fromUser($user, $abilities);

        $offices = $user === null
            ? []
            : Office::query()
                ->visibleTo($user)
                ->where('active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData(
                    id: $office->id,
                    name: $office->name,
                    city: $office->city,
                ))
                ->all();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => new AuthData(
                user: $userData,
                role: $userData?->role,
            ),
            'flash' => FlashData::fromSession($request->session()),
            'settings' => SettingsData::fromCurrentSettings(),
            'offices' => $offices,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
