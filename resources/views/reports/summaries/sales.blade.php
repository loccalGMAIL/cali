@use('App\Support\Money')

@if ($report['rows'] !== [])
    @php
        $totals = $report['totals'];
        $unit = match ($report['group_by']) {
            'product' => $totals['quantity'] === 1 ? 'unidad' : 'unidades',
            default => $totals['quantity'] === 1 ? 'venta' : 'ventas',
        };
    @endphp

    <div class="cali-report-summary" style="margin-top: 0">
        <table>
            <caption>Total ({{ $totals['quantity'] }} {{ $unit }})</caption>
            <tr>
                <td>Total vendido</td>
                <td class="number">{{ Money::format($totals['total']) }}</td>
            </tr>
            <tr>
                <td>Costo</td>
                <td class="number">{{ Money::format($totals['cost']) }}</td>
            </tr>
            <tr>
                <td>Flete</td>
                <td class="number">{{ Money::format($totals['freight']) }}</td>
            </tr>
            <tr>
                <td><strong>Ganancia</strong></td>
                <td class="number"><strong>{{ Money::format($totals['profit']) }}</strong></td>
            </tr>
            <tr>
                <td>Margen</td>
                <td class="number">{{ number_format((float) $totals['margin'], 1, ',', '.') }}%</td>
            </tr>
        </table>
    </div>
@endif
