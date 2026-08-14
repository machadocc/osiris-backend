<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavingsGoal\StoreSavingsGoalRequest;
use App\Http\Requests\SavingsGoal\UpdateSavingsGoalRequest;
use App\Http\Resources\SavingsGoalResource;
use App\Models\SavingsGoal;
use Illuminate\Http\Request;

class SavingsGoalController extends Controller
{
    public function index(Request $request)
    {
        return SavingsGoalResource::collection(
            $request->user()->savingsGoals()->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreSavingsGoalRequest $request)
    {
        $goal = $request->user()->savingsGoals()->create($request->validated());

        return new SavingsGoalResource($goal);
    }

    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal)
    {
        $this->authorizeOwnership($request, $savingsGoal);

        $savingsGoal->update($request->validated());

        return new SavingsGoalResource($savingsGoal);
    }

    public function destroy(Request $request, SavingsGoal $savingsGoal)
    {
        $this->authorizeOwnership($request, $savingsGoal);

        $savingsGoal->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, SavingsGoal $savingsGoal): void
    {
        abort_unless($savingsGoal->user_id === $request->user()->id, 403);
    }
}
