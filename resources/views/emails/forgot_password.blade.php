<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - Folium UNIMAR</title>
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
            margin-bottom: 30px;
        }
        .btn:hover {
            background-color: #1d3070;
        }
        .note {
            font-size: 12px;
            color: #8E8D9B;
            margin-top: 20px;
            background-color: #141127;
            border: 1px solid #2A2640;
            padding: 15px;
            border-radius: 12px;
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
        <div class="header">
            <img src="{{ env('FRONTEND_URL') }}/logo-text.png" alt="Folium" style="height: 38px; width: auto; vertical-align: middle; display: inline-block;">
        </div>
        
        <h1>Hola, {{ $student->name }}</h1>
        
        <p>
            Recibimos una solicitud para restablecer la contraseña de tu cuenta estudiantil en la plataforma Folium.
        </p>
        
        <p>
            Haz clic en el siguiente enlace para crear una nueva contraseña para tu cuenta:
        </p>
        
        <a href="{{ env('FRONTEND_URL') }}/reset-password?token={{ $token }}&email={{ urlencode($student->email) }}" class="btn" target="_blank">Restablecer mi contraseña</a>
        
        <div class="note">
            Si no solicitaste este cambio, simplemente ignora este correo y tu contraseña actual seguirá funcionando de manera segura.
        </div>
        
        <div class="footer">
            Este correo es enviado de forma automática por el sistema de seguridad de Folium UNIMAR.<br>
            © {{ date('Y') }} Decanato de Humanidades, Universidad de Margarita. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
