<!-- resources/views/emails/layouts/email.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <style>
        /* Estilos comunes para todos los correos */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
        }

        .email-wrapper {
            background-color: #f4f4f4;
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-content {
            padding: 40px 30px;
        }

        h1 {
            color: #333333;
            font-size: 24px;
            margin-bottom: 20px;
            margin-top: 0;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
            margin-top: 0;
            color: #555555;
        }

        .button {
            display: inline-block;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: bold;
            color: #ffffff !important;
            background: linear-gradient(135deg, #10b981, #059669);
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }

        .button:hover {
            background: linear-gradient(135deg, #059669, #047857);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .long-url {
            word-wrap: break-word;
            word-break: break-all;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            color: #475569;
        }

        .long-url a {
            color: #059669;
            text-decoration: none;
        }

        .long-url a:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 14px;
        }

        .code p {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            white-space: pre;
            color: #333;
            overflow-x: auto;
        }

        .new-code p {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            padding: 0;
            white-space: pre-wrap;
            color: #333;
            overflow-x: auto;
            line-height: 1.2;
        }

        .expiry-notice {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 12px 16px;
            color: #92400e;
            font-size: 14px;
            margin: 20px 0;
        }

        .expiry-notice strong {
            color: #b45309;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="email-content">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
