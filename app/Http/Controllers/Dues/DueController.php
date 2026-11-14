<?php

namespace App\Http\Controllers\Dues;

use App\Actions\Dues\CancelDue;
use App\Actions\Dues\CreateManualDue;
use App\Actions\Dues\PostponeDue;
use App\Data\DueInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dues\CancelDueRequest;
use App\Http\Requests\Dues\DueRequest;
use App\Http\Requests\Dues\PostponeDueRequest;
use App\Models\Client;
use App\Models\Due;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DueController extends Controller
{
    public function store(DueRequest $request, Client $client, CreateManualDue $create): RedirectResponse
    {
        $this->authorize('view', $client);
        $this->authorize('create', Due::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $create->handle($actor, $client, DueInput::fromRequest($request));

        return back(303)->with('success', __('Due created.'));
    }

    public function postpone(PostponeDueRequest $request, Due $due, PostponeDue $postpone): RedirectResponse
    {
        $due->loadMissing('client:id,name,account_manager_id');
        $this->authorize('postpone', $due);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $validated = $request->validated();
        $postpone->handle($due, $validated['new_due_on'], $validated['reason'], $actor);

        return back(303)->with('success', __('Due postponed.'));
    }

    public function cancel(CancelDueRequest $request, Due $due, CancelDue $cancel): RedirectResponse
    {
        $due->loadMissing('client:id,name,account_manager_id');
        $this->authorize('cancel', $due);

        $cancel->handle($due, (string) $request->validated('reason'));

        return back(303)->with('success', __('Due canceled.'));
    }
}
