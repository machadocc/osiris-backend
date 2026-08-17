<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringTransaction\StoreRecurringTransactionRequest;
use App\Http\Requests\RecurringTransaction\UpdateRecurringTransactionRequest;
use App\Http\Resources\RecurringTransactionResource;
use App\Models\RecurringTransaction;
use Illuminate\Http\Request;

class RecurringTransactionController extends Controller
{
    public function index(Request $request)
    {
        $recurringTransactions = $request->user()->recurringTransactions()
            ->with(['category', 'account'])
            ->orderBy('day_of_month')
            ->get();

        return RecurringTransactionResource::collection($recurringTransactions);
    }

    public function store(StoreRecurringTransactionRequest $request)
    {
        $data = $request->validated();
        $data['active'] ??= true;

        $recurringTransaction = $request->user()->recurringTransactions()->create($data);

        return new RecurringTransactionResource($recurringTransaction->load(['category', 'account']));
    }

    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurringTransaction)
    {
        $this->authorizeOwnership($request, $recurringTransaction);

        $recurringTransaction->update($request->validated());

        return new RecurringTransactionResource($recurringTransaction->load(['category', 'account']));
    }

    public function destroy(Request $request, RecurringTransaction $recurringTransaction)
    {
        $this->authorizeOwnership($request, $recurringTransaction);

        $recurringTransaction->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, RecurringTransaction $recurringTransaction): void
    {
        abort_unless($recurringTransaction->user_id === $request->user()->id, 403);
    }
}
