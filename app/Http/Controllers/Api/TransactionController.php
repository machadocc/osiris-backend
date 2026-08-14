<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['sometimes', new Enum(TransactionType::class)],
        ]);

        $transactions = $request->user()->transactions()
            ->with(['category', 'account'])
            ->when($request->filled('month'), function ($query) use ($request) {
                $reference = $request->date('month', 'Y-m');

                $query->whereMonth('date', $reference->month)->whereYear('date', $reference->year);
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('type'), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('type', $request->string('type'))))
            ->when($request->filled('search'), fn ($query) => $query->where('description', 'ilike', '%'.$request->string('search').'%'))
            ->orderByDesc('date')
            ->paginate(20);

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $data = $request->validated();
        unset($data['receipt']);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store("receipts/{$request->user()->id}", 'public');
        }

        $transaction = $request->user()->transactions()->create($data);

        return new TransactionResource($transaction->load(['category', 'account']));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $this->authorizeOwnership($request, $transaction);

        $data = $request->validated();
        unset($data['receipt'], $data['remove_receipt']);

        if ($request->hasFile('receipt')) {
            $this->deleteReceipt($transaction->receipt_path);
            $data['receipt_path'] = $request->file('receipt')->store("receipts/{$request->user()->id}", 'public');
        } elseif ($request->boolean('remove_receipt')) {
            $this->deleteReceipt($transaction->receipt_path);
            $data['receipt_path'] = null;
        }

        $transaction->update($data);

        return new TransactionResource($transaction->load(['category', 'account']));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->authorizeOwnership($request, $transaction);

        $this->deleteReceipt($transaction->receipt_path);
        $transaction->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
    }

    private function deleteReceipt(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
