@use('App\Support\Money')

@if ($report['groups'] !== [])
    <div class="cali-report-summary" style="margin-top: 0">
        <table>
            <caption>Total a cobrar ({{ $report['count'] }} {{ $report['count'] === 1 ? 'cuota' : 'cuotas' }})</caption>
            <tr>
                <td>Vence en el mes</td>
                <td class="number">{{ Money::format($report['month_amount']) }}</td>
            </tr>
            <tr>
                <td>Atrasado de meses anteriores</td>
                <td class="number">{{ Money::format($report['previous_amount']) }}</td>
            </tr>
            <tr>
                <td><strong>Total</strong></td>
                <td class="number"><strong>{{ Money::format($report['total']) }}</strong></td>
            </tr>
        </table>
    </div>
@endif
