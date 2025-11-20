<?php

return [
    'navigation_label' => 'Application Settings',
    'navigation_group' => 'System',
    'title' => 'Application Settings',

    'tabs' => [
        'settings' => 'Settings',
        'branding' => 'Branding',
        'contact' => 'Contact',
        'legal' => 'Legal',
    ],

    'sections' => [
        'company_branding' => [
            'title' => 'Company Branding',
            'description' => 'Customize your company appearance in the application',
        ],
        'support_information' => [
            'title' => 'Support Information',
            'description' => 'Configure support contact details',
        ],
        'legal_links' => [
            'title' => 'Legal Links',
            'description' => 'Configure legal compliance URLs',
        ],
    ],

    'fields' => [
        'company_logo_light' => [
            'label' => 'Logo for Light Theme',
            'helper' => 'Logo that will be displayed in light mode. Formats: PNG, JPG, SVG, WEBP. Max 2MB. Recommended: dark or colored logo on transparent background.',
        ],
        'company_logo_dark' => [
            'label' => 'Logo for Dark Theme',
            'helper' => 'Logo that will be displayed in dark mode. Formats: PNG, JPG, SVG, WEBP. Max 2MB. Recommended: light or white logo on transparent background.',
        ],
        'company_name' => [
            'label' => 'Company Name',
            'helper' => 'Company name displayed in UI and emails',
        ],
        'support_email' => [
            'label' => 'Support Email',
            'helper' => 'Email address for customer support',
        ],
        'support_url' => [
            'label' => 'Support URL',
            'helper' => 'URL to your support/help desk system',
        ],
        'privacy_policy_url' => [
            'label' => 'Privacy Policy URL',
            'helper' => 'Link to your privacy policy page',
        ],
        'terms_url' => [
            'label' => 'Terms of Service URL',
            'helper' => 'Link to your terms of service page',
        ],
        'data_protection_url' => [
            'label' => 'Data Protection URL',
            'helper' => 'Link to your data protection information',
        ],
    ],

    'actions' => [
        'save' => 'Save Settings',
    ],

    'notifications' => [
        'saved' => [
            'title' => 'Settings saved',
            'body' => 'Application settings have been updated successfully.',
        ],
        'error' => [
            'title' => 'Error',
            'body' => 'Failed to save settings: :message',
        ],
    ],
];
