<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('simple_unblock.mail.not_blocked.title') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d1ecf1; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .support-box { background: #f8f9fa; border: 2px solid #0d6efd; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center; }
        .support-box a { display: inline-block; background: #0d6efd; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px; }
        .support-box a:hover { background: #0b5ed7; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        h1 { color: #0c5460; margin: 0; }
        h2 { color: #495057; margin-top: 25px; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ __('simple_unblock.mail.not_blocked.title') }}</h1>
        </div>

        <div class="content">
            <p>{{ __('simple_unblock.mail.greeting') }}</p>

            <div class="info">
                <h2 style="margin-top: 0;">{{ __('simple_unblock.mail.not_blocked.result_title') }}</h2>
                <p><strong>{{ __('simple_unblock.mail.not_blocked.result_message', ['ip' => $ip, 'domain' => $domain]) }}</strong></p>
            </div>

            <h2>{{ __('simple_unblock.mail.not_blocked.analysis_title') }}</h2>
            <ul>
                <li>{{ __('simple_unblock.mail.not_blocked.check_1') }}</li>
                <li>{{ __('simple_unblock.mail.not_blocked.check_2') }}</li>
                <li>{{ __('simple_unblock.mail.not_blocked.check_3') }}</li>
            </ul>

            <h2>{{ __('simple_unblock.mail.not_blocked.possible_causes_title') }}</h2>
            <ul>
                <li>{{ __('simple_unblock.mail.not_blocked.cause_1') }}</li>
                <li>{{ __('simple_unblock.mail.not_blocked.cause_2') }}</li>
                <li>{{ __('simple_unblock.mail.not_blocked.cause_3') }}</li>
                <li>{{ __('simple_unblock.mail.not_blocked.cause_4') }}</li>
            </ul>

            @if($supportTicketUrl)
                <div class="support-box">
                    <h2 style="margin-top: 0; color: #0d6efd;">{{ __('simple_unblock.mail.not_blocked.need_help') }}</h2>
                    <p>{{ __('simple_unblock.mail.not_blocked.support_message') }}</p>
                    <a href="{{ $supportTicketUrl }}">{{ __('simple_unblock.mail.not_blocked.open_ticket') }}</a>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>{{ __('simple_unblock.mail.footer_thanks') }}<br>{{ __('simple_unblock.mail.footer_company', ['company' => $companyName]) }}</p>
        </div>
    </div>
</body>
</html>

