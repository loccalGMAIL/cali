<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Electrodomésticos en cuotas</title>
    <style>
        :root { color-scheme: light dark; --bg: #fafafa; --fg: #1f2937; --muted: #6b7280; --accent: #d97706; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111827; --fg: #f3f4f6; --muted: #9ca3af; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem 16px;
               font-family: system-ui, sans-serif; background: var(--bg); color: var(--fg); }
        main { max-width: 36rem; text-align: center; }
        h1 { font-size: 2.5rem; margin: 0 0 .5rem; }
        p { color: var(--muted); line-height: 1.6; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: .75rem 1.75rem; border-radius: .5rem;
                background: var(--accent); color: #fff; text-decoration: none; font-weight: 600; }
        footer { margin-top: 3rem; font-size: .875rem; color: var(--muted); }
    </style>
</head>
<body>
<main>
    <h1>{{ config('app.name') }}</h1>
    <p>Electrodomésticos para tu hogar, con financiación propia y cuotas a tu medida.</p>
    <a class="btn" href="{{ request()->getScheme() }}://{{ config('domains.panel') }}{{ request()->getPort() && ! in_array(request()->getPort(), [80, 443]) ? ':'.request()->getPort() : '' }}">Ingresar</a>
    <footer>Tel: (completar) · Dirección: (completar)</footer>
</main>
</body>
</html>
