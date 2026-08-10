<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $request->user()->transactions()
            ->with('category')
            ->when($request->filled('month'), function ($query) use ($request) {
                $reference = $request->date('month', 'Y-m');

                $query->whereMonth('date', $reference->month)->whereYear('date', $reference->year);
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderByDesc('date')
            ->paginate(20);

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = $request->user()->transactions()->create($request->validated());

        return new TransactionResource($transaction->load('category'));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $this->authorizeOwnership($request, $transaction);

        $transaction->update($request->validated());

        return new TransactionResource($transaction->load('category'));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->authorizeOwnership($request, $transaction);

        $transaction->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
    }
}
