<?php

namespace App\Http\Controllers\Dues;

use App\Actions\Dues\BuildDueReminder;
use App\Http\Controllers\Controller;
use App\Models\Due;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DueReminderController extends Controller
{
    public function store(Request $request, Due $due, BuildDueReminder $build): JsonResponse
    {
        $due->loadMissing('client:id,name,phone,account_manager_id');
        $this->authorize('remind', $due);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return response()->json($build->handle($actor, $due));
    }
}
