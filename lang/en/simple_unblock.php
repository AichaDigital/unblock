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

    // Two-step flow (v1.2.0)
    'step1_label' => 'Request',
    'step2_label' => 'Verify',
    'send_otp_button' => 'Send Verification Code',
    'otp_sent' => 'Verification code sent! Check your email.',
    'otp_label' => 'Verification Code',
    'otp_help' => 'Enter the 6-digit code sent to your email',
    'verify_button' => 'Verify and Unblock',
    'back_button' => 'Back',
    'sending' => 'Sending...',
    'verifying' => 'Verifying...',

    'submit_button' => 'Verify and Unblock',
    'processing' => 'Processing...',
    'process_button' => 'Process Unblock',

    'processing_message' => 'Your request is being processed. You will receive an email with the results shortly.',
    'success_message' => 'Request processed! You will receive an email with system details and possible unblock information.',
    'error_message' => 'An error occurred. Please try again later.',

    'rate_limit_exceeded' => 'You have exceeded the request limit. Please wait :seconds seconds.',

    // Domain validation (Phase 3 - v1.5.0)
    'domain_not_found' => 'Domain not found in our system. Please verify that you entered it correctly.',
    'account_suspended' => 'The account associated with this domain is currently suspended. Please contact support.',
    'account_deleted' => 'The account associated with this domain no longer exists. Please contact support.',

    'generic_response' => 'We have received your request and are processing it.',
    'no_information_found' => 'No information found for the provided data.',
    'help_text' => 'Verify your identity with the code received to process your IP unblock.',
    'otp_verified_ready' => 'Code verified successfully!',

    'mail' => [
        'success_subject' => 'IP Unblocked - :domain',
        'admin_alert_subject' => 'Alert: Simple Unblock Attempt - :domain',
    ],
];
