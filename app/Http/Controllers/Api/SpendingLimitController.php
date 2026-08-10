<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpendingLimit\StoreSpendingLimitRequest;
use App\Http\Requests\SpendingLimit\UpdateSpendingLimitRequest;
use App\Http\Resources\SpendingLimitResource;
use App\Models\SpendingLimit;
use Illuminate\Http\Request;

class SpendingLimitController extends Controller
{
    public function index(Request $request)
    {
        $spendingLimits = $request->user()->spendingLimits()
            ->with('category')
            ->when($request->filled('month'), function ($query) use ($request) {
                $reference = $request->date('month', 'Y-m');

                $query->whereMonth('reference_month', $reference->month)
                    ->whereYear('reference_month', $reference->year);
            })
            ->orderByDesc('reference_month')
            ->get();

        return SpendingLimitResource::collection($spendingLimits);
    }

    public function store(StoreSpendingLimitRequest $request)
    {
        $spendingLimit = $request->user()->spendingLimits()->create($request->validated());

        return new SpendingLimitResource($spendingLimit->load('category'));
    }

    public function update(UpdateSpendingLimitRequest $request, SpendingLimit $spendingLimit)
    {
        $this->authorizeOwnership($request, $spendingLimit);

        $spendingLimit->update($request->validated());

        return new SpendingLimitResource($spendingLimit->load('category'));
    }

    public function destroy(Request $request, SpendingLimit $spendingLimit)
    {
        $this->authorizeOwnership($request, $spendingLimit);

        $spendingLimit->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, SpendingLimit $spendingLimit): void
    {
        abort_unless($spendingLimit->user_id === $request->user()->id, 403);
    }
}
