<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #141127; margin: 0; padding: 0; line-height: 1.4; }
        .header { background: #273E92; padding: 20px 28px 14px; }
        .header-brand { font-family: sans-serif; color: #ED6C31; font-size: 20px; font-weight: bold; }
        .header-title { font-family: sans-serif; color: #ffffff; font-size: 14px; font-weight: bold; margin-top: 4px; }
        .header-meta { color: #C5E4E4; font-size: 8px; margin-top: 3px; }
        .header-divider { height: 3px; background: #ED6C31; }
        .content { padding: 20px 28px; }
        .section-title { font-size: 11px; font-weight: bold; color: #273E92; margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1.5px solid #C5E4E4; }
        .full-cards { margin-bottom: 16px; }
        .full-cards table { width: 100%; border-collapse: collapse; }
        .full-cards td { text-align: center; padding: 7px 5px; border: 1px solid #C5E4E4; width: 16.66%; }
        .card-number { font-size: 14px; font-weight: bold; color: #273E92; }
        .card-label { font-size: 6.5px; color: #666; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8px; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #273E92; color: white; padding: 6px 8px; text-align: left; font-size: 7px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.data td { padding: 5px 8px; border-bottom: 1px solid #C5E4E4; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f5f7ff; }
        .footer { background: #141127; color: #8E8D9B; font-size: 7px; text-align: center; padding: 10px 28px; position: fixed; bottom: 0; left: 0; right: 0; }
        .footer strong { color: #C5E4E4; }
        .num { text-align: right; }
        .small-table { width: auto; min-width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="data:image/png;base64,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AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA" alt="Folium" width="74" style="vertical-align: middle;" />
        <div class="header-title">{{ $title }}</div>
        <div class="header-meta">Generado: {{ $generated_at }} @if ($date_from !== '—') | Período: {{ $date_from }} - {{ $date_to }} @endif | Reporte: {{ $period }}</div>
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
                <tr><td style="font-family: 'DejaVu Sans Mono', monospace; font-size: 7px;">{{ $page['path'] }}</td><td class="num">{{ $page['views'] }}</td></tr>
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
