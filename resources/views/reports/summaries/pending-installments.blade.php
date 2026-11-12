@use('App\Support\Money')

@if ($report['rows'] !== [])
    <div class="cali-report-scroll">
        <table class="cali-report">
            <thead>
            <tr>
                <th></th>
                @foreach ($report['columns'] as $label)
                    <th class="number">{{ $label }}</th>
                @endforeach
                <th class="number">Total</th>
            </tr>
            </thead>
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
