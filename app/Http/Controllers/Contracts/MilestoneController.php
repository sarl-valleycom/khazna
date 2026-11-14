<?php

namespace App\Http\Controllers\Contracts;

use App\Actions\Milestones\MarkMilestoneDelivered;
use App\Actions\Milestones\MarkMilestoneRequested;
use App\Actions\Milestones\RebalanceMilestones;
use App\Enums\ContractType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestones\MilestonesRequest;
use App\Models\Contract;
use App\Models\Milestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MilestoneController extends Controller
{
    public function deliver(Contract $contract, Milestone $milestone, MarkMilestoneDelivered $deliver): RedirectResponse
    {
        $this->authorizeMilestone($contract, $milestone);

        $deliver->handle($milestone);

        return back(303)->with('success', __('Milestone marked as delivered.'));
    }

    public function request(Contract $contract, Milestone $milestone, MarkMilestoneRequested $request): RedirectResponse
    {
        $this->authorizeMilestone($contract, $milestone);

        $request->handle($milestone);

        return back(303)->with('success', __('Marked as requested.'));
    }

    /** What the amounts would become, without saving anything. */
    public function preview(MilestonesRequest $request, Contract $contract, RebalanceMilestones $rebalance): JsonResponse
    {
        $this->authorizeProject($contract);

        return response()->json([
            'plan' => $rebalance->handle($contract, $request->validated('milestones'), apply: false),
        ]);
    }

    public function update(MilestonesRequest $request, Contract $contract, RebalanceMilestones $rebalance): RedirectResponse
    {
        $this->authorizeProject($contract);

        $rebalance->handle($contract, $request->validated('milestones'), apply: true);

        return back(303)->with('success', __('Milestones updated.'));
    }

    private function authorizeMilestone(Contract $contract, Milestone $milestone): void
    {
        $this->authorizeProject($contract);

        abort_unless($milestone->contract_id === $contract->id, 404);
    }

    private function authorizeProject(Contract $contract): void
    {
        $contract->loadMissing('client:id,name,account_manager_id');
        $this->authorize('manageMilestones', $contract);

        abort_unless($contract->type === ContractType::CustomProject, 404);
    }
}
