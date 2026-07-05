<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicación del Decanato - Folium UNIMAR</title>
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
        .message-body {
            background-color: #141127;
            border: 1px solid #2A2640;
            border-radius: 16px;
            padding: 24px;
            margin: 24px 0;
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.6;
            color: #E5DEFE;
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
            <h1>Comunicación del Decanato</h1>
        </div>

        <p>Estimado(a) <strong>{{ $student->name }}</strong>,</p>

        <p>Has recibido un mensaje de la administración de Folium UNIMAR:</p>

        <div class="message-body">{{ $messageText }}</div>

        <p>Si tienes alguna duda, puedes contactar directamente con el Decanato de Humanidades.</p>

        <div class="footer">
            Este correo es enviado de forma automática por el sistema de gestión estudiantil de Folium UNIMAR.<br>
            &copy; {{ date('Y') }} Decanato de Humanidades, Universidad de Margarita. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
