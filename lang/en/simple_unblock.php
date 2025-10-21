<?php

declare(strict_types=1);

return [
    'title' => 'Quick IP Unblock',
    'subtitle' => 'Enter your IP and domain to verify and unblock if necessary',

    'ip_label' => 'IP Address',
    'ip_placeholder' => '192.168.1.1',
    'domain_label' => 'Domain',
    'domain_placeholder' => 'example.com (without www)',
    'email_label' => 'Email',
    'email_placeholder' => 'your@email.com',

    'submit_button' => 'Verify and Unblock',
    'processing' => 'Processing...',

    'processing_message' => 'Your request is being processed. You will receive an email with the results shortly.',
    'error_message' => 'An error occurred. Please try again later.',

    'rate_limit_exceeded' => 'You have exceeded the request limit. Please wait :seconds seconds.',

    'generic_response' => 'We have received your request and are processing it.',
    'no_information_found' => 'No information found for the provided data.',

    'mail' => [
        'success_subject' => 'IP Unblocked - :domain',
        'admin_alert_subject' => 'Alert: Simple Unblock Attempt - :domain',
    ],
];
