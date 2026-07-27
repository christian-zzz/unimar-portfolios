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
        .cards { margin-bottom: 16px; }
        .cards table { width: 100%; border-collapse: collapse; }
        .cards td { text-align: center; padding: 8px 6px; border: 1px solid #C5E4E4; width: 16.66%; }
        .card-number { font-size: 18px; font-weight: bold; color: #273E92; }
        .card-label { font-size: 8.5px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .month-table { width: auto; min-width: 180px; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9.5px; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #273E92; color: white; padding: 6px 8px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.data td { padding: 5px 8px; border-bottom: 1px solid #C5E4E4; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f5f7ff; }
        .status-published { color: #059669; font-weight: bold; }
        .status-draft { color: #d97706; font-weight: bold; }
        .status-nostarted { color: #dc2626; font-weight: bold; }
        .footer { background: #141127; color: #8E8D9B; font-size: 8.5px; text-align: center; padding: 10px 28px; position: fixed; bottom: 0; left: 0; right: 0; }
        .footer strong { color: #C5E4E4; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <img src="{{ public_path('logo-text.png') }}" style="height: 28px; display: block; margin-bottom: 4px;">
                    <div class="header-title">{{ $title }}</div>
                    <div class="header-meta">Generado: {{ $generated_at }} @if ($date_from !== '—') | Período: {{ $date_from }} - {{ $date_to }} @endif</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <img src="{{ public_path('unimarlogoorange.png') }}" style="height: 24px; display: block; margin-left: auto;">
                </td>
            </tr>
        </table>
    </div>
    <div class="header-divider"></div>

    <div class="content">
        <div class="section-title">Resumen General</div>
        <div class="cards">
            <table>
                <tr>
                    <td><div class="card-number">{{ $total }}</div><div class="card-label">Total</div></td>
                    <td><div class="card-number">{{ $active }}</div><div class="card-label">Activos</div></td>
                    <td><div class="card-number">{{ $inactive }}</div><div class="card-label">Inactivos</div></td>
                    <td><div class="card-number">{{ $published }}</div><div class="card-label">Publicados</div></td>
                    <td><div class="card-number">{{ $draft }}</div><div class="card-label">Borradores</div></td>
                    <td><div class="card-number">{{ $nostarted }}</div><div class="card-label">Sin iniciar</div></td>
                </tr>
            </table>
        </div>

        @if (!empty($registrations_by_month))
        <div class="section-title">Registros por Mes</div>
            <table class="data month-table">
                <thead>
                    <tr><th>Mes</th><th>Registros</th></tr>
                </thead>
                <tbody>
                @foreach ($registrations_by_month as $month)
                    <tr><td>{{ $month['month'] }}</td><td>{{ $month['count'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">Listado de Estudiantes</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Portafolio</th>
                    <th>Último Acceso</th>
                    <th>Registro</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($students as $student)
                <tr>
                    <td><strong>{{ $student['name'] }}</strong></td>
                    <td style="color: #666;">{{ $student['email'] }}</td>
                    <td class="
                        @php
                            $s = strtolower($student['portfolio_status']);
                            echo $s === 'publicado' ? 'status-published' : ($s === 'borrador' ? 'status-draft' : 'status-nostarted');
                        @endphp
                    ">{{ $student['portfolio_status'] }}</td>
                    <td>{{ $student['last_login'] }}</td>
                    <td>{{ $student['created_at'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="height: 30px;"></div>
    </div>

    <div class="footer">
        <strong>folium.</strong> &mdash; UNIMAR &mdash; Plataforma de Portafolios &mdash; Documento generado automáticamente
    </div>
</body>
</html>
