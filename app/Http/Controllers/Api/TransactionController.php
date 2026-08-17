<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\PushNotificationService;
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
        $transaction->load(['category', 'account']);

        $this->notifyIfLimitExceeded($transaction);

        return new TransactionResource($transaction);
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
        $transaction->load(['category', 'account']);

        $this->notifyIfLimitExceeded($transaction);

        return new TransactionResource($transaction);
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

    /**
     * Notifica por push (RF-TRX-06 é o aviso em tempo real no formulário,
     * antes de enviar; isso aqui é o complemento server-side, depois que o
     * lançamento já foi salvo) quando algum limite de gastos afetado por
     * essa transação chega a 100% ou mais.
     */
    private function notifyIfLimitExceeded(Transaction $transaction): void
    {
        if ($transaction->category->type !== TransactionType::Expense) {
            return;
        }

        $limits = $transaction->user->spendingLimits()
            ->where(fn ($query) => $query->whereNull('category_id')->orWhere('category_id', $transaction->category_id))
            ->whereYear('reference_month', $transaction->date->year)
            ->whereMonth('reference_month', $transaction->date->month)
            ->get();

        foreach ($limits as $limit) {
            $spent = $limit->spentAmount();
            $percentage = $limit->limit_amount > 0 ? ($spent / (float) $limit->limit_amount) * 100 : 0;

            if ($percentage >= 100) {
                PushNotificationService::notifyUser(
                    $transaction->user,
                    'Limite estourado!',
                    sprintf(
                        '"%s" chegou a %d%% — %s de %s já gastos.',
                        $limit->name,
                        round($percentage),
                        $this->formatCurrency($spent),
                        $this->formatCurrency((float) $limit->limit_amount),
                    ),
                    '/spending-limits',
                );
            }
        }
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
