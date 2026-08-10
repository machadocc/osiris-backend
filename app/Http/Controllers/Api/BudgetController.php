<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $budgets = $request->user()->budgets()
            ->with('category')
            ->when($request->filled('month'), function ($query) use ($request) {
                $reference = $request->date('month', 'Y-m');

                $query->whereMonth('reference_month', $reference->month)
                    ->whereYear('reference_month', $reference->year);
            })
            ->orderByDesc('reference_month')
            ->get();

        return BudgetResource::collection($budgets);
    }

    public function store(StoreBudgetRequest $request)
    {
        $budget = $request->user()->budgets()->create($request->validated());

        return new BudgetResource($budget->load('category'));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget)
    {
        $this->authorizeOwnership($request, $budget);

        $budget->update($request->validated());

        return new BudgetResource($budget->load('category'));
    }

    public function destroy(Request $request, Budget $budget)
    {
        $this->authorizeOwnership($request, $budget);

        $budget->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Budget $budget): void
    {
        abort_unless($budget->user_id === $request->user()->id, 403);
    }
}
