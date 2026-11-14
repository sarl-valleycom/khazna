<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** The signed-in user. Routes using this sit behind auth, so a missing user is a 403, never a crash. */
    protected function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }

    /**
     * A query-string value as text, or '' when it is missing or not text. Filters come from the
     * address bar, where anyone can type ?from[]=x; reading that as a string would be a 500.
     */
    protected function queryString(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }
}
