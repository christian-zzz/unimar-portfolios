<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña - Folium UNIMAR</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #141127;
            color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #1C1835;
            border: 1px solid #2A2640;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #2A2640;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: 800;
            color: #C5E4E4;
            text-decoration: none;
            letter-spacing: -0.05em;
        }
        .logo span {
            color: #ED6C31;
        }
        h1 {
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            margin-top: 0;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #E5DEFE;
        }
        .credentials-card {
            background-color: #141127;
            border: 1px solid #2A2640;
            border-radius: 16px;
            padding: 24px;
            margin: 24px 0;
        }
        .credential-field {
            margin-bottom: 12px;
        }
        .credential-label {
            font-size: 11px;
            font-weight: 600;
            color: #FFB598;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .credential-value {
            font-family: monospace;
            font-size: 15px;
            color: #C5E4E4;
            background-color: #1C1835;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #2A2640;
            display: inline-block;
        }
        .btn {
            display: block;
            text-align: center;
            background-color: #273E92;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 30px;
        }
        .btn:hover {
            background-color: #1d3070;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #2A2640;
            padding-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #8E8D9B;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="text-align: center; border-bottom: 1px solid #2A2640; padding-bottom: 20px; margin-bottom: 30px;">
            <img src="{{ env('FRONTEND_URL') }}/logo.png" alt="Folium Logo" style="height: 38px; width: auto; vertical-align: middle; display: inline-block;">
        </div>
        
        <h1>Hola, {{ $student->name }}</h1>
        
        <p>
            Te informamos que la administración de Folium UNIMAR ha restablecido las credenciales de acceso para tu cuenta estudiantil.
        </p>
        
        <p>
            A partir de este momento, tus nuevas credenciales de ingreso son:
        </p>
        
        <div class="credentials-card">
            <div class="credential-field">
                <div class="credential-label">Correo Electrónico</div>
                <div class="credential-value">{{ $student->email }}</div>
            </div>
            <div class="credential-field" style="margin-bottom: 0;">
                <div class="credential-label">Nueva Contraseña Temporal</div>
                <div class="credential-value">{{ $password }}</div>
            </div>
        </div>
        
        <p>
            Por seguridad, te recomendamos ingresar e inmediatamente cambiar esta contraseña temporal desde los ajustes de tu perfil.
        </p>
        
        <a href="{{ env('FRONTEND_URL') }}/login" class="btn" target="_blank">Iniciar Sesión en Folium</a>
        
        <div class="footer">
            Este correo es enviado de forma automática por el sistema de gestión de contraseñas de Folium UNIMAR.<br>
            © {{ date('Y') }} Decanato de Humanidades, Universidad de Margarita. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
