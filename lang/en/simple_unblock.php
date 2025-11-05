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
    'request_submitted' => 'Request submitted successfully! You will receive an email with the analysis results in a few minutes. Check your inbox.',
    'error_message' => 'An error occurred. Please try again later.',
    'cooldown_active' => 'Please wait :seconds seconds before submitting another request.',

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

        // Email body translations
        'admin_copy_badge' => '[ADMIN COPY]',
        'title_success' => 'IP Unblocked Successfully',
        'title_admin_alert' => 'Simple Unblock Attempt Alert',

        'admin_info_intro' => 'This is an admin copy of the user notification.',
        'admin_info_user_email' => 'User Email',
        'admin_info_domain' => 'Domain',
        'admin_info_ip' => 'IP Address',
        'admin_info_host' => 'Host',
        'admin_info_report_id' => 'Report ID',

        'greeting' => 'Hello,',
        'success_message' => 'Your IP address <strong>:ip</strong> for domain <strong>:domain</strong> has been successfully analyzed and unblocked.',

        'analysis_results_title' => 'Analysis Results',
        'analysis_status_label' => 'Status',
        'analysis_status_value' => 'IP was blocked and has been unblocked',
        'analysis_server_label' => 'Server',
        'analysis_timestamp_label' => 'Timestamp',

        'firewall_logs_title' => 'Firewall Logs',
        'firewall_logs_intro' => 'The following services had blocks:',
        'firewall_logs_multiple' => 'Multiple entries',

        'block_details_title' => 'Block Details',
        'block_reason' => 'Reason',
        'block_attempts' => 'Attempts',
        'block_location' => 'Location',
        'block_since' => 'Blocked since',
        'block_timeframe' => 'in the last :seconds seconds',

        'next_steps_title' => 'What\'s Next?',
        'next_steps_message' => 'Your IP should now have access to the server. If you continue experiencing issues, please contact our support team.',

        'admin_notes_title' => 'Admin Notes',
        'admin_note_anonymous' => 'This was an anonymous simple unblock request',
        'admin_note_email' => 'Email provided: :email',
        'admin_note_domain_validated' => 'Domain validated in server logs',
        'admin_note_ip_confirmed' => 'IP was confirmed blocked before unblocking',

        'footer_thanks' => 'Thanks,',
        'footer_company' => ':company',
        'footer_security_notice' => 'If you didn\'t request this unblock, please contact :supportEmail immediately.',

        // Admin alert specific translations
        'admin_alert' => [
            'reason_label' => 'Reason',
            'unknown' => 'Unknown',
            'multiple_hosts' => 'Multiple hosts checked',
            'request_details_title' => 'Request Details',
            'reason_details_title' => 'Reason Details',
            'action_label' => 'Action',

            'blocked_no_domain_title' => 'IP was blocked but domain was NOT found in server logs',
            'blocked_no_domain_description' => 'This could indicate: User provided wrong domain, Domain doesn\'t exist on this server, or Possible abuse attempt.',
            'no_unblock_silent' => 'No unblock performed (silent)',

            'domain_found_not_blocked_title' => 'Domain was found but IP was NOT blocked',
            'domain_found_not_blocked_description' => 'The user\'s IP is not currently blocked in the firewall.',
            'no_unblock_needed' => 'No unblock needed (silent)',

            'no_match_title' => 'No match found',
            'no_match_description' => 'Neither IP blocked nor domain found on any server.',
            'no_action_silent' => 'No action taken (silent)',

            'job_failure_title' => 'Job execution failed',
            'error_label' => 'Error',
            'unknown_error' => 'Unknown error',
            'review_logs' => 'Review logs and investigate',

            'unknown_reason' => 'Unknown reason',
            'analysis_data_title' => 'Analysis Data',
            'ip_blocked_label' => 'IP Blocked',
            'yes' => 'Yes',
            'no' => 'No',
            'logs_preview_title' => 'Logs Preview',
            'note_label' => 'Note',
            'user_not_notified' => 'The user was NOT notified about this attempt (silent logging).',
            'system_suffix' => 'System',
        ],
    ],
];
