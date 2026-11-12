@use('App\Support\Money')

@if ($report['payments']->isEmpty())
    <p class="cali-report-empty">No hay cobros en el período.</p>
@else
    <div class="cali-report-scroll">
        <table class="cali-report">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Cobro</th>
                <th>Cliente</th>
                <th>Concepto</th>
                <th>Medio</th>
                <th>Cobró</th>
                <th class="number">Monto</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($report['payments'] as $payment)
                <tr>
                    <td>{{ $payment->date->format('d/m/Y') }}</td>
                    <td>#{{ $payment->id }}</td>
                    <td>{{ $payment->customer->name ?? '—' }}</td>
                    <td>{{ $payment->isDownPayment() ? "Anticipo venta #{$payment->sale_id}" : 'Cobro de cuotas' }}</td>
                    <td>{{ $payment->method->getLabel() }}</td>
                    <td>{{ $payment->collector->name ?? '—' }}</td>
                    <td class="number">{{ Money::format($payment->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td colspan="6">Total cobrado ({{ $report['payments']->count() }} {{ $report['payments']->count() === 1 ? 'cobro' : 'cobros' }})</td>
                <td class="number">{{ Money::format($report['total']) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div class="cali-report-summary">
        <table>
            <caption>Por medio de pago</caption>
            @foreach ($report['by_method'] as $label => $amount)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="number">{{ Money::format($amount) }}</td>
                </tr>
            @endforeach
        </table>
        <table>
            <caption>Por quien cobró</caption>
            @foreach ($report['by_collector'] as $label => $amount)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="number">{{ Money::format($amount) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
