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
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            padding: 40px 30px 30px 30px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px 30px 40px 30px;
        }
        .intro-text {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
            margin-top: 0;
            color: #555555;
            font-family: Arial, sans-serif;
        }
        .otp-code {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code-label {
            font-size: 14px;
            color: #1e40af;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-code-value {
            font-size: 42px;
            font-weight: bold;
            color: #1e3a8a;
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
            color: #2563eb;
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
            <div class="header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); padding: 40px 30px 30px 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">{{ __('one-time-passwords::notifications.title') }}</h1>
            </div>

            <div class="content" style="padding: 30px 30px 40px 30px;">
                <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px; margin-top: 0; color: #555555; font-family: Arial, sans-serif;">{{ __('one-time-passwords::notifications.intro', ['url' => config('app.url')]) }}</p>

                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 14px; color: #475569; word-wrap: break-word; word-break: break-all; margin: 20px 0;">
                    <a href="{{ config('app.url') }}" style="color: #2563eb; text-decoration: none;">{{ config('app.url') }}</a>
                </div>

                <div style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 2px solid #3b82f6; border-radius: 8px; padding: 30px; text-align: center; margin: 30px 0;">
                    <div style="font-size: 14px; color: #1e40af; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">{{ __('one-time-passwords::notifications.title') }}</div>
                    {{-- text-4xl equivalent (36px) for better readability --}}
                    <div style="font-size: 36px; font-weight: bold; color: #1e3a8a; font-family: 'Courier New', Courier, monospace; letter-spacing: 8px; margin: 10px 0; user-select: all;">{{ $oneTimePassword->password }}</div>
                </div>

                <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0; color: #92400e; font-size: 14px;">{{ __('one-time-passwords::notifications.outro') }}</p>
                </div>

                {{-- Modern Footer with Legal Links (Tailwind semantic sizes) --}}
                <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
                    {{-- Signature: text-sm (14px) for both "Gracias" and company name --}}
                    <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569;">
                        <strong>{{ __('Gracias') }},</strong><br>
                        <strong>{{ config('company.name') }}</strong>
                    </p>
                    {{-- Legal links: text-xs (12px) - same size as copyright --}}
                    <p style="margin: 15px 0; font-size: 12px; color: #64748b;">
                        <a href="{{ config('company.legal.privacy_policy_url') }}" style="color: #64748b; text-decoration: none; margin: 0 8px;">{{ __('Política de Privacidad') }}</a> |
                        <a href="{{ config('company.legal.terms_url') }}" style="color: #64748b; text-decoration: none; margin: 0 8px;">{{ __('Términos de Uso') }}</a> |
                        <a href="{{ config('company.legal.data_protection_url') }}" style="color: #64748b; text-decoration: none; margin: 0 8px;">{{ __('Protección de Datos') }}</a>
                    </p>
                    {{-- Copyright: text-xs (12px) --}}
                    <p style="margin: 10px 0 0 0; font-size: 12px; color: #94a3b8;">
                        &copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('Todos los derechos reservados') }}.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
