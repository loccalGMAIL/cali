<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 12mm; }
        body { margin: 0; padding: 32px 16px; background: #fff; color: #111827; font: 12px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .sheet { max-width: {{ $landscape ? '1400px' : '900px' }}; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 20px; }
        .muted { color: #4b5563; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 2px 16px; margin: 0 0 16px; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        .actions { margin-bottom: 24px; }
        button { font: inherit; padding: 8px 16px; border: 1px solid #111827; border-radius: 6px; background: #111827; color: #fff; cursor: pointer; }

        .cali-report-scroll { overflow-x: auto; }
        .cali-report { width: 100%; border-collapse: collapse; }
        .cali-report th, .cali-report td { padding: 4px 6px; border-bottom: 1px solid #d1d5db; text-align: left; vertical-align: top; }
        .cali-report th { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; white-space: nowrap; }
        .cali-report .number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cali-report tfoot td, .cali-report .cali-report-subtotal td { font-weight: 700; }
        .cali-report tfoot td { border-top: 2px solid #111827; border-bottom: 0; }
        .cali-report .cali-report-group td { padding-top: 10px; background: #f3f4f6; font-weight: 600; }
        .cali-report .cali-report-danger { color: #b91c1c; font-weight: 600; }
        .cali-report a { color: inherit; text-decoration: none; }
        .cali-report-muted { color: #4b5563; font-weight: 400; }
        .cali-report-empty { padding: 24px 0; color: #4b5563; }
        .cali-report-summary { display: flex; flex-wrap: wrap; gap: 32px; margin-top: 20px; }
        .cali-report-summary table { border-collapse: collapse; }
        .cali-report-summary caption { text-align: left; font-weight: 700; padding-bottom: 4px; }
        .cali-report-summary td { padding: 2px 16px 2px 0; }
        .cali-report-summary td.number { text-align: right; font-variant-numeric: tabular-nums; padding-right: 0; }

        @media print {
            body { padding: 0; font-size: 10px; }
            .sheet { max-width: none; }
            .actions { display: none; }
            .cali-report-scroll { overflow: visible; }
            .cali-report thead { display: table-header-group; }
            .cali-report tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>

    <header>
        <div>
            <h1>{{ $title }}</h1>
            <div class="muted">{{ config('app.name') }}</div>
        </div>
        <div class="muted">Emitido el {{ now()->format('d/m/Y H:i') }}</div>
    </header>

    @if ($description !== [])
        <dl>
            @foreach ($description as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    @endif

    @include($partial, ['report' => $report, 'filters' => $filters, 'screen' => false])
</div>
</body>
</html>
