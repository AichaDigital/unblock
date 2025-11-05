<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('one-time-passwords::notifications.title') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
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
        .header {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 40px 30px;
        }
        .otp-code {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code-label {
            font-size: 14px;
            color: #047857;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-code-value {
            font-size: 42px;
            font-weight: bold;
            color: #065f46;
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: 8px;
            margin: 10px 0;
            user-select: all;
        }
        .url-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            color: #475569;
            word-wrap: break-word;
            word-break: break-all;
            margin: 20px 0;
        }
        .url-box a {
            color: #059669;
            text-decoration: none;
        }
        .url-box a:hover {
            text-decoration: underline;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 14px;
            text-align: center;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
            margin-top: 0;
            color: #555555;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <h1>{{ __('one-time-passwords::notifications.title') }}</h1>
            </div>

            <div class="content">
                <p>{{ __('one-time-passwords::notifications.intro', ['url' => config('app.url')]) }}</p>

                <div class="url-box">
                    <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
                </div>

                <div class="otp-code">
                    <div class="otp-code-label">{{ __('one-time-passwords::notifications.title') }}</div>
                    <div class="otp-code-value">{{ $oneTimePassword->password }}</div>
                </div>

                <div class="warning">
                    <p>{{ __('one-time-passwords::notifications.outro') }}</p>
                </div>

                <div class="footer">
                    <p>{{ __('Gracias') }},<br>{{ config('company.name') }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
