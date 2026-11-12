@use('App\Support\Money')

@php
    $bySale = $report['group_by'] === 'sale';
    $quantityLabel = $report['group_by'] === 'product' ? 'Unidades' : 'Ventas';
    $percent = fn (string $margin): string => number_format((float) $margin, 1, ',', '.').'%';
@endphp

@if ($report['rows'] === [])
    <p class="cali-report-empty">No hay ventas en el período.</p>
@else
    <div class="cali-report-scroll">
        <table class="cali-report">
            <thead>
            <tr>
                @if ($bySale)
                    <th>Fecha</th>
                    <th>Venta</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="number">Cuotas</th>
                    <th class="number">Anticipo</th>
                @else
                    <th>{{ $report['group_by'] === 'product' ? 'Producto' : 'Vendedor' }}</th>
                    <th class="number">{{ $quantityLabel }}</th>
                @endif
                <th class="number">Total</th>
                <th class="number">Costo</th>
                <th class="number">Flete</th>
                <th class="number">Ganancia</th>
                <th class="number">Margen</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($report['rows'] as $row)
                <tr>
                    @if ($bySale)
                        <td>{{ $row['sale']->date->format('d/m/Y') }}</td>
                        <td>{{ $row['sale']->number() }}</td>
                        <td>{{ $row['sale']->customer->name ?? '—' }}</td>
                        <td>{{ $row['sale']->seller->name ?? '—' }}</td>
                        <td class="number">{{ $row['sale']->installments_count }}</td>
                        <td class="number">{{ Money::format($row['sale']->down_payment) }}</td>
                    @else
                        <td>{{ $row['label'] }}</td>
                        <td class="number">{{ $row['quantity'] }}</td>
                    @endif
                    <td class="number">{{ Money::format($row['total']) }}</td>
                    <td class="number">{{ Money::format($row['cost']) }}</td>
                    <td class="number">{{ Money::format($row['freight']) }}</td>
                    <td class="number">{{ Money::format($row['profit']) }}</td>
                    <td class="number">{{ $percent($row['margin']) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                @if ($bySale)
                    <td colspan="6">Total ({{ $report['totals']['quantity'] }} {{ $report['totals']['quantity'] === 1 ? 'venta' : 'ventas' }})</td>
                @else
                    <td>Total</td>
                    <td class="number">{{ $report['totals']['quantity'] }}</td>
                @endif
                <td class="number">{{ Money::format($report['totals']['total']) }}</td>
                <td class="number">{{ Money::format($report['totals']['cost']) }}</td>
                <td class="number">{{ Money::format($report['totals']['freight']) }}</td>
                <td class="number">{{ Money::format($report['totals']['profit']) }}</td>
                <td class="number">{{ $percent($report['totals']['margin']) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
@endif
