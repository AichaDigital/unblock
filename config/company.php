<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Configure your company information and external links used throughout
    | the application for legal compliance, support, and documentation.
    |
    */

    'name' => env('COMPANY_NAME', 'Your Company Name'),

    'support' => [
        'email' => env('SUPPORT_EMAIL', 'support@example.com'),
        'url' => env('SUPPORT_URL', 'https://support.example.com'),
        'ticket_url' => env('SUPPORT_TICKET_URL', 'https://support.example.com/tickets'),
    ],

    'legal' => [
        'privacy_policy_url' => env('LEGAL_PRIVACY_URL', 'https://example.com/privacy'),
        'terms_url' => env('LEGAL_TERMS_URL', 'https://example.com/terms'),
        'data_protection_url' => env('LEGAL_DATA_PROTECTION_URL', 'https://example.com/data-protection'),
    ],

    'wiki' => [
        'base_url' => env('WIKI_BASE_URL', 'https://wiki.example.com'),
        'firewall' => [
            'csf' => env('WIKI_CSF_URL', ''),
            'bfm' => env('WIKI_BFM_URL', ''),
            'exim' => env('WIKI_EXIM_URL', ''),
            'dovecot' => env('WIKI_DOVECOT_URL', ''),
            'modsecurity' => env('WIKI_MODSECURITY_URL', ''),
            'unblock_guide' => env('WIKI_UNBLOCK_GUIDE_URL', ''),
        ],
    ],

    'contact' => [
        'hours' => env('SUPPORT_HOURS', 'Monday to Friday, 9:00 - 18:00'),
    ],
];

