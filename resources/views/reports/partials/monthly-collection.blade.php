@use('App\Filament\Resources\Customers\CustomerResource')
@use('App\Support\Money')

@if ($report['groups'] === [])
    <p class="cali-report-empty">No hay cuotas para cobrar con estos filtros.</p>
@else
    <div class="cali-report-scroll">
        <table class="cali-report">
            <thead>
            <tr>
                <th>Venta</th>
                <th>Cuota</th>
                <th>Vencimiento</th>
                <th class="number">Días de atraso</th>
                <th class="number">Saldo</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($report['groups'] as $group)
                @php($customer = $group['customer'])
                <tr class="cali-report-group">
                    <td colspan="5">
                        @if ($screen)
                            <a href="{{ CustomerResource::getUrl('view', ['record' => $customer]) }}">{{ $customer->name }}</a>
                        @else
                            {{ $customer->name }}
                        @endif
                        <span class="cali-report-muted">
                            @if ($customer->phone) · Tel. {{ $customer->phone }}@endif
                            @if ($customer->address) · {{ $customer->address }}@endif
                            @if ($customer->city) · {{ $customer->city }}@endif
                        </span>
                    </td>
                </tr>
                @foreach ($group['lines'] as $line)
                    <tr>
                        <td>{{ \App\Models\Sale::numberFor($line['installment']->sale_id) }}</td>
                        <td>{{ $line['installment']->number }}/{{ $line['installment']->sale->installments_count }}</td>
                        <td @class(['cali-report-danger' => $line['days_late'] > 0])>{{ $line['installment']->due_date->format('d/m/Y') }}</td>
                        <td @class(['number', 'cali-report-danger' => $line['days_late'] > 0])>{{ $line['days_late'] > 0 ? $line['days_late'] : '–' }}</td>
                        <td class="number">{{ Money::format($line['balance']) }}</td>
                    </tr>
                @endforeach
                @if (count($group['lines']) > 1)
                    <tr class="cali-report-subtotal">
                        <td colspan="4">Subtotal {{ $customer->name }}</td>
                        <td class="number">{{ Money::format($group['subtotal']) }}</td>
                    </tr>
                @endif
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td colspan="4">Total a cobrar ({{ $report['count'] }} {{ $report['count'] === 1 ? 'cuota' : 'cuotas' }})</td>
                <td class="number">{{ Money::format($report['total']) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div class="cali-report-summary">
        <table>
            <caption>Resumen</caption>
            <tr>
                <td>Vence en el mes</td>
                <td class="number">{{ Money::format($report['month_amount']) }}</td>
            </tr>
            <tr>
                <td>Atrasado de meses anteriores</td>
                <td class="number">{{ Money::format($report['previous_amount']) }}</td>
            </tr>
        </table>
    </div>
@endif
