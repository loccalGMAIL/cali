@use('App\Support\Money')

@if ($report['payments']->isNotEmpty())
    <div class="cali-report-summary" style="margin-top: 0">
        <table>
            <caption>Total</caption>
            <tr>
                <td>Total cobrado ({{ $report['payments']->count() }} {{ $report['payments']->count() === 1 ? 'cobro' : 'cobros' }})</td>
                <td class="number">{{ Money::format($report['total']) }}</td>
            </tr>
        </table>
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
