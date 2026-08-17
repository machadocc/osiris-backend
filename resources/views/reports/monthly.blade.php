<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 2px 0; color: #0f172a; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .subtitle { font-size: 12px; color: #64748b; margin: 0 0 16px 0; }
        .meta { font-size: 10px; color: #94a3b8; margin-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 5px 6px; font-size: 10px; }
        thead th { background: #f1f5f9; color: #475569; font-weight: bold; border-bottom: 1px solid #cbd5e1; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; }

        .summary-table td { border: none; padding: 10px 12px; }
        .summary-box { background: #f8fafc; border-radius: 4px; }
        .summary-label { font-size: 10px; color: #64748b; }
        .summary-value { font-size: 16px; font-weight: bold; margin-top: 2px; }
        .income { color: #059669; }
        .expense { color: #dc2626; }
        .balance-positive { color: #0f172a; }
        .balance-negative { color: #dc2626; }

        .swatch { display: inline-block; width: 8px; height: 8px; border-radius: 4px; margin-right: 4px; }
        .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; }
        .empty { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <h1>Osiris — Relatório financeiro mensal</h1>
    <p class="subtitle">{{ $monthLabel }} · {{ $user->name }}</p>

    <table class="summary-table">
        <tr>
            <td width="33%">
                <div class="summary-box">
                    <div class="summary-label">Receitas</div>
                    <div class="summary-value income">R$ {{ number_format($income, 2, ',', '.') }}</div>
                </div>
            </td>
            <td width="33%">
                <div class="summary-box">
                    <div class="summary-label">Despesas</div>
                    <div class="summary-value expense">R$ {{ number_format($expense, 2, ',', '.') }}</div>
                </div>
            </td>
            <td width="33%">
                <div class="summary-box">
                    <div class="summary-label">Saldo do mês</div>
                    <div class="summary-value {{ $balance >= 0 ? 'balance-positive' : 'balance-negative' }}">
                        R$ {{ number_format($balance, 2, ',', '.') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <h2>Gastos por categoria</h2>
    @if ($expensesByCategory->isEmpty())
        <p class="empty">Nenhuma despesa neste mês.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="num">Total</th>
                    <th class="num">% das despesas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expensesByCategory as $category)
                    <tr>
                        <td><span class="swatch" style="background: {{ $category->color }}"></span>{{ $category->name }}</td>
                        <td class="num">R$ {{ number_format($category->total_amount, 2, ',', '.') }}</td>
                        <td class="num">{{ $expense > 0 ? number_format(($category->total_amount / $expense) * 100, 1, ',', '.') : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Limites de gastos</h2>
    @if ($spendingLimits->isEmpty())
        <p class="empty">Nenhum limite definido para este mês.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Limite</th>
                    <th>Categoria</th>
                    <th class="num">Gasto</th>
                    <th class="num">Limite</th>
                    <th class="num">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($spendingLimits as $limit)
                    <tr>
                        <td>{{ $limit->name }}</td>
                        <td>{{ $limit->category->name ?? 'Todas as categorias' }}</td>
                        <td class="num">R$ {{ number_format($limit->spentAmount(), 2, ',', '.') }}</td>
                        <td class="num">R$ {{ number_format($limit->limit_amount, 2, ',', '.') }}</td>
                        <td class="num">{{ $limit->limit_amount > 0 ? number_format(min($limit->spentAmount() / $limit->limit_amount, 1) * 100, 1, ',', '.') : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Metas de economia</h2>
    @if ($savingsGoals->isEmpty())
        <p class="empty">Nenhuma meta de economia cadastrada.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Meta</th>
                    <th class="num">Guardado</th>
                    <th class="num">Alvo</th>
                    <th class="num">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($savingsGoals as $goal)
                    <tr>
                        <td>{{ $goal->name }}</td>
                        <td class="num">R$ {{ number_format($goal->current_amount, 2, ',', '.') }}</td>
                        <td class="num">R$ {{ number_format($goal->target_amount, 2, ',', '.') }}</td>
                        <td class="num">{{ $goal->percentage() }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Lançamentos do mês ({{ $transactions->count() }})</h2>
    @if ($transactions->isEmpty())
        <p class="empty">Nenhum lançamento neste mês.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th width="10%">Data</th>
                    <th width="20%">Categoria</th>
                    <th width="20%">Conta</th>
                    <th width="35%">Descrição</th>
                    <th width="15%" class="num">Valor</th>
                </tr>
            </thead>
            <tbody>
                @php $typeOf = fn ($t) => $t->category?->type ?? $t->splits->first()?->category?->type; @endphp
                @foreach ($transactions as $transaction)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($transaction->date)->format('d/m/Y') }}</td>
                        <td>{{ $transaction->category->name ?? 'Múltiplas categorias' }}</td>
                        <td>{{ $transaction->account->name }}</td>
                        <td>{{ $transaction->description ?: '-' }}</td>
                        <td class="num {{ $typeOf($transaction)?->value === 'income' ? 'income' : 'expense' }}">
                            {{ $typeOf($transaction)?->value === 'income' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">Gerado automaticamente pelo Osiris em {{ \Illuminate\Support\Carbon::now()->format('d/m/Y H:i') }}.</p>
</body>
</html>
