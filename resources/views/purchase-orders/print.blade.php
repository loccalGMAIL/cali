<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orden de compra #{{ $order->id }} · {{ $order->supplier->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background: #fff; color: #111827; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .sheet { max-width: 760px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 22px; }
        .muted { color: #4b5563; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; margin: 0 0 24px; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #d1d5db; text-align: left; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; }
        td.number, th.number { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: 700; border-bottom: 0; }
        .notes { margin-top: 24px; white-space: pre-line; }
        .actions { margin-bottom: 24px; }
        button { font: inherit; padding: 8px 16px; border: 1px solid #111827; border-radius: 6px; background: #111827; color: #fff; cursor: pointer; }
        @media print { body { padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>

    <header>
        <div>
            <h1>Orden de compra #{{ $order->id }}</h1>
            <div class="muted">{{ config('app.name') }}</div>
        </div>
        <div class="muted">{{ $order->date->format('d/m/Y') }}</div>
    </header>

    <dl>
        <dt>Proveedor</dt>
        <dd>{{ $order->supplier->name }}@if ($order->supplier->cuit) · CUIT {{ $order->supplier->cuit }}@endif</dd>
        @if ($order->supplier->phone)
            <dt>Teléfono</dt>
            <dd>{{ $order->supplier->phone }}</dd>
        @endif
        <dt>Estado</dt>
        <dd>{{ $order->status->getLabel() }}</dd>
    </dl>

    <table>
        <thead>
        <tr>
            <th>Código</th>
            <th>Producto</th>
            <th class="number">Cantidad</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line['product']->sku ?? '—' }}</td>
                <td>{{ $line['product']->name }}@if ($line['product']->brand) <span class="muted">· {{ $line['product']->brand }}</span>@endif</td>
                <td class="number">{{ $line['quantity'] }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="2">Total de unidades</td>
            <td class="number">{{ $lines->sum('quantity') }}</td>
        </tr>
        </tfoot>
    </table>

    @if ($order->notes)
        <p class="notes"><strong>Notas:</strong> {{ $order->notes }}</p>
    @endif
</div>
</body>
</html>
