<!-- resources/views/emails/layouts/email.blade.php -->
{{--
    Clean email layout with NO CSS classes - only structure
    All styling MUST be inline in child templates
    Following Tailwind semantic sizes for consistency
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
    {{-- Email wrapper: gray background --}}
    <div style="background-color: #f4f4f4; padding: 40px 20px;">
        {{-- Container: white card with shadow --}}
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden;">
            {{-- Content area with padding --}}
            <div style="padding: 40px 30px;">
                @yield('content')

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
