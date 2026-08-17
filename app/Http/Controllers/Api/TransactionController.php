<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\DashboardCache;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with(['category', 'account', 'splits.category'])
            ->when($request->filled('month'), function ($query) use ($request) {
                $reference = $request->date('month', 'Y-m');

                $query->whereMonth('date', $reference->month)->whereYear('date', $reference->year);
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $categoryId = $request->integer('category_id');

                // Transação dividida (RF-TRX-13) casa com o filtro se QUALQUER split for dessa categoria.
                $query->where(fn ($q) => $q
                    ->where('category_id', $categoryId)
                    ->orWhereHas('splits', fn ($split) => $split->where('category_id', $categoryId)));
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $type = $request->string('type');

                $query->where(fn ($q) => $q
                    ->whereHas('category', fn ($category) => $category->where('type', $type))
                    ->orWhereHas('splits.category', fn ($category) => $category->where('type', $type)));
            })
            ->when($request->filled('search'), fn ($query) => $query->where('description', 'ilike', '%'.$request->string('search').'%'))
            ->orderByDesc('date')
            ->paginate(20);

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $data = $request->validated();
        unset($data['receipt']);
        $splits = $data['splits'] ?? null;
        unset($data['splits']);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store("receipts/{$request->user()->id}", 'public');
        }

        $transaction = DB::transaction(function () use ($request, $data, $splits) {
            $transaction = $request->user()->transactions()->create($data);

            if ($splits) {
                $transaction->splits()->createMany($splits);
            }

            return $transaction;
        });

        $transaction->load(['category', 'account', 'splits.category']);

        $this->notifyIfLimitExceeded($transaction);

        return new TransactionResource($transaction);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $this->authorizeOwnership($request, $transaction);

        $data = $request->validated();
        unset($data['receipt'], $data['remove_receipt']);
        $splits = $data['splits'] ?? null;
        unset($data['splits']);

        if ($request->hasFile('receipt')) {
            $this->deleteReceipt($transaction->receipt_path);
            $data['receipt_path'] = $request->file('receipt')->store("receipts/{$request->user()->id}", 'public');
        } elseif ($request->boolean('remove_receipt')) {
            $this->deleteReceipt($transaction->receipt_path);
            $data['receipt_path'] = null;
        }

        DB::transaction(function () use ($transaction, $data, $splits, $request) {
            $transaction->update($data);

            // Enviar splits substitui totalmente os antigos; enviar category_id
            // (sem splits) volta a transação pro modo de categoria única.
            if ($splits) {
                $transaction->splits()->delete();
                $transaction->splits()->createMany($splits);
            } elseif ($request->filled('category_id')) {
                $transaction->splits()->delete();
            }
        });

        // Editar só os splits (sem tocar amount/date/description/category_id)
        // pode deixar o `update($data)` com um array vazio, e o Eloquent não
        // dispara o evento 'updated' quando não há atributo sujo — invalida
        // explicitamente pra não deixar o dashboard com cache desatualizado.
        DashboardCache::invalidate($transaction->user_id);

        $transaction->load(['category', 'account', 'splits.category']);

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
        // Transação dividida (RF-TRX-13) não tem category_id — o tipo e as
        // categorias afetadas vêm dos splits, que já compartilham o mesmo type.
        $categoryIds = $transaction->category_id
            ? [$transaction->category_id]
            : $transaction->splits->pluck('category_id')->all();

        if (empty($categoryIds)) {
            return;
        }

        $type = $transaction->category?->type ?? $transaction->splits->first()?->category?->type;
        if ($type !== TransactionType::Expense) {
            return;
        }

        $limits = $transaction->user->spendingLimits()
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereIn('category_id', $categoryIds))
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
                    'osiris-limit-alert',
                    '📊 Ver limites',
                );
            }
        }
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
