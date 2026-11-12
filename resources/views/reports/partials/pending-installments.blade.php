@use('App\Filament\Resources\Customers\CustomerResource')
@use('App\Services\Reports\PendingInstallmentsReport')
@use('App\Support\Money')

@if ($report['rows'] === [])
    <p class="cali-report-empty">No hay cuotas pendientes con estos filtros.</p>
@else
    <div class="cali-report-scroll">
        <table class="cali-report">
            <thead>
            <tr>
                <th>Cliente</th>
                @foreach ($report['columns'] as $label)
                    <th class="number">{{ $label }}</th>
                @endforeach
                <th class="number">Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($report['rows'] as $row)
                <tr>
                    <td>
                        @if ($screen)
                            <a href="{{ CustomerResource::getUrl('view', ['record' => $row['customer']]) }}">{{ $row['customer']->name }}</a>
                        @else
                            {{ $row['customer']->name }}
                        @endif
                    </td>
                    @foreach ($row['cells'] as $column => $amount)
                        <td @class(['number', 'cali-report-danger' => $column === PendingInstallmentsReport::OVERDUE && (float) $amount > 0])>{{ Money::cell($amount) }}</td>
                    @endforeach
                    <td class="number"><strong>{{ Money::cell($row['total']) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td>Total ({{ count($report['rows']) }} {{ count($report['rows']) === 1 ? 'cliente' : 'clientes' }})</td>
                @foreach ($report['totals'] as $amount)
                    <td class="number">{{ Money::cell($amount) }}</td>
                @endforeach
                <td class="number">{{ Money::cell($report['total']) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
@endif
