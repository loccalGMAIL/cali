@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de pago {{ $receipt['number'] }}</title>
    <style>
        @page { margin: 32px 40px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .header { border-bottom: 2px solid #111827; margin-bottom: 18px; }
        .header .logo { width: 96px; padding: 0 14px 12px 0; }
        .header .logo img { max-width: 96px; max-height: 72px; }
        .header .business { padding-bottom: 12px; }
        .header .business strong { font-size: 14px; }
        .header .title { text-align: right; padding-bottom: 12px; white-space: nowrap; }
        .header .title h1 { margin: 0 0 2px; font-size: 16px; letter-spacing: .04em; text-transform: uppercase; }
        .muted { color: #4b5563; }
        .facts td { padding: 3px 0; }
        .facts .label { width: 120px; font-weight: bold; }
        .amount { font-size: 15px; font-weight: bold; }
        .section { margin-top: 16px; }
        .section h2 { margin: 0 0 2px; font-size: 12px; }
        .section .products { margin-bottom: 6px; }
        .lines th, .lines td { padding: 5px 6px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .lines th { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; }
        .lines .number { text-align: right; white-space: nowrap; }
        .remaining { margin-top: 6px; padding: 6px 8px; background: #f3f4f6; font-weight: bold; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #4b5563; }
        .cancelled { position: fixed; top: 300px; left: 0; right: 0; text-align: center; font-size: 96px; font-weight: bold; color: #dc2626; opacity: .18; transform: rotate(-30deg); }
        .cancelled-note { color: #dc2626; font-weight: bold; margin-bottom: 12px; }
    </style>
</head>
<body>
@if ($receipt['is_cancelled'])
    <div class="cancelled">ANULADO</div>
@endif

<div class="footer">Comprobante no válido como factura · {{ $receipt['number'] }}</div>

<table class="header">
    <tr>
        @if ($business['receipt_shows_business_header'])
            @if ($logo)
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td class="business">
                @if ($business['business_name'])
                    <strong>{{ $business['business_name'] }}</strong><br>
                @endif
                @if ($business['business_tax_id'])
                    <span class="muted">CUIT {{ $business['business_tax_id'] }}</span><br>
                @endif
                @if ($business['business_address'])
                    <span class="muted">{{ $business['business_address'] }}</span><br>
                @endif
                @if ($business['business_phone'] || $business['business_email'])
                    <span class="muted">{{ collect([$business['business_phone'], $business['business_email']])->filter()->implode(' · ') }}</span>
                @endif
            </td>
        @endif
        <td class="title">
            <h1>Comprobante de pago</h1>
            <div>N° {{ $receipt['number'] }}</div>
            <div class="muted">{{ $receipt['date'] }}</div>
        </td>
    </tr>
</table>

@if ($receipt['is_cancelled'])
    <div class="cancelled-note">Este cobro fue anulado.</div>
@endif

<table class="facts">
    <tr>
        <td class="label">Cliente</td>
        <td>{{ $receipt['customer_name'] }}@if ($receipt['customer_document']) · DNI/CUIT {{ $receipt['customer_document'] }}@endif</td>
    </tr>
    <tr>
        <td class="label">Medio de pago</td>
        <td>{{ $receipt['method'] }}</td>
    </tr>
    <tr>
        <td class="label">Monto recibido</td>
        <td class="amount">{{ Money::format($receipt['amount']) }}</td>
    </tr>
</table>

@foreach ($receipt['sales'] as $sale)
    <div class="section">
        <h2>Venta {{ $sale['number'] }}</h2>
        <div class="products muted">{{ $sale['products'] }}</div>

        <table class="lines">
            <thead>
            <tr>
                <th>Concepto</th>
                <th class="number">Importe</th>
            </tr>
            </thead>
            <tbody>
            @if ($receipt['is_down_payment'])
                <tr>
                    <td>Anticipo</td>
                    <td class="number">{{ Money::format($receipt['amount']) }}</td>
                </tr>
            @endif
            @foreach ($sale['lines'] as $line)
                <tr>
                    <td>Cuota {{ $line['number'] }} de {{ $sale['installments_count'] }}@if ($line['partial']) <span class="muted">(pago parcial)</span>@endif</td>
                    <td class="number">{{ Money::format($line['amount']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="remaining">
            @if ($sale['remaining'] === 0)
                Plan completo: no quedan cuotas pendientes.
            @else
                Cuotas pendientes: {{ $sale['remaining'] }} de {{ $sale['installments_count'] }}
            @endif
        </div>
    </div>
@endforeach
</body>
</html>
