<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #141127; margin: 0; padding: 0; line-height: 1.4; }
        .header { background: #273E92; padding: 20px 28px 14px; }
        .header table { width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0; }
        .header td { border: none; padding: 0; vertical-align: middle; }
        .header-title { font-family: sans-serif; color: #ffffff; font-size: 16px; font-weight: bold; margin-top: 4px; }
        .header-meta { color: #C5E4E4; font-size: 9.5px; margin-top: 3px; }
        .header-divider { height: 3px; background: #ED6C31; }
        .content { padding: 20px 28px; }
        .section-title { font-size: 13px; font-weight: bold; color: #273E92; margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1.5px solid #C5E4E4; }
        .full-cards { margin-bottom: 16px; }
        .full-cards table { width: 100%; border-collapse: collapse; }
        .full-cards td { text-align: center; padding: 7px 5px; border: 1px solid #C5E4E4; width: 16.66%; }
        .card-number { font-size: 15px; font-weight: bold; color: #273E92; }
        .card-label { font-size: 7.5px; color: #666; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9.5px; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #273E92; color: white; padding: 6px 8px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.data td { padding: 5px 8px; border-bottom: 1px solid #C5E4E4; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f5f7ff; }
        .footer { background: #141127; color: #8E8D9B; font-size: 8.5px; text-align: center; padding: 10px 28px; position: fixed; bottom: 0; left: 0; right: 0; }
        .footer strong { color: #C5E4E4; }
        .num { text-align: right; }
        .small-table { width: auto; min-width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <img src="{{ public_path('logo-text.png') }}" style="height: 28px; display: block; margin-bottom: 4px;">
                    <div class="header-title">{{ $title }}</div>
                    <div class="header-meta">Generado: {{ $generated_at }} @if ($date_from !== '—') | Período: {{ $date_from }} - {{ $date_to }} @endif | Reporte: {{ $period }}</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <img src="{{ public_path('unimarlogoorange.png') }}" style="height: 24px; display: block; margin-left: auto;">
                </td>
            </tr>
        </table>
    </div>
    <div class="header-divider"></div>

    <div class="content">
        @if (!empty($totals))
        <div class="section-title">Métricas Generales</div>
        <div class="full-cards">
            <table>
                <tr>
                    <td><div class="card-number">{{ $totals['activeUsers'] ?? 0 }}</div><div class="card-label">Usuarios Activos</div></td>
                    <td><div class="card-number">{{ $totals['screenPageViews'] ?? 0 }}</div><div class="card-label">Vistas</div></td>
                    <td><div class="card-number">{{ $totals['newUsers'] ?? 0 }}</div><div class="card-label">Nuevos Usuarios</div></td>
                    <td><div class="card-number">{{ isset($totals['averageSessionDuration']) ? number_format($totals['averageSessionDuration'], 0) . 's' : '0s' }}</div><div class="card-label">Duración Prom.</div></td>
                    <td><div class="card-number">{{ $totals['bounceRate'] ?? '0%' }}</div><div class="card-label">Rebote</div></td>
                    <td><div class="card-number">{{ $engagement_rate ?? '0%' }}</div><div class="card-label">Engagement</div></td>
                </tr>
            </table>
        </div>
        @endif

        @if (!empty($devices))
        <div class="section-title">Dispositivos</div>
        <table class="data small-table">
            <thead>
                <tr><th>Dispositivo</th><th class="num">Usuarios</th></tr>
            </thead>
            <tbody>
            @foreach ($devices as $device)
                <tr><td>{{ $device['name'] }}</td><td class="num">{{ $device['value'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif

        @if (!empty($countries))
        <div class="section-title">Países</div>
        <table class="data small-table">
            <thead>
                <tr><th>Código</th><th class="num">Usuarios</th></tr>
            </thead>
            <tbody>
            @foreach ($countries as $country)
                <tr><td>{{ $country['code'] }}</td><td class="num">{{ $country['users'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif

        @if (!empty($top_pages))
        <div class="section-title">Páginas Más Visitadas</div>
        <table class="data">
            <thead>
                <tr><th>Ruta</th><th class="num">Vistas</th></tr>
            </thead>
            <tbody>
            @foreach ($top_pages as $page)
                <tr><td style="font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5px;">{{ $page['path'] }}</td><td class="num">{{ $page['views'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif

        @if (!empty($events))
        <div class="section-title">Eventos</div>
        <table class="data small-table">
            <thead>
                <tr><th>Evento</th><th class="num">Conteo</th></tr>
            </thead>
            <tbody>
            @foreach ($events as $event)
                <tr><td>{{ $event['name'] }}</td><td class="num">{{ $event['count'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif

        <div style="height: 30px;"></div>
    </div>

    <div class="footer">
        <strong>folium.</strong> &mdash; UNIMAR &mdash; Datos obtenidos de Google Analytics 4 &mdash; Documento generado automáticamente
    </div>
</body>
</html>
