<?php

return [
    // Actions
    'actions' => [
        'check' => 'Check Firewall',
        'cancel' => 'Cancel',
        'processing' => 'Processing...',
        'close' => 'Close',
        'new_check' => 'New Check',
    ],

    // Status
    'status' => [
        'request_submitted' => 'Request Submitted',
        'submitted_message' => 'Your firewall query is being processed. You will receive a notification when it\'s ready.',
        'ip_checked' => 'IP Checked',
        'target_checked' => 'Target Checked',
    ],

    // Help System
    'help' => [
        'need_help' => 'Need help?',
        'domain_explanation' => [
            'title' => 'What is a domain?',
            'description' => 'A domain is the name of your website (e.g., mycompany.com)',
            'examples' => 'Examples: yourdomain.com, blog.example.es',
            'note' => 'Select the domain where you have the access problem',
        ],
        'server_explanation' => [
            'title' => 'What is a server?',
            'description' => 'A server is the machine where your websites are hosted',
            'examples' => 'Examples: server1.hosting.com, vps-madrid-01',
            'note' => 'Select the server if you manage multiple domains on it',
        ],
        'ip_explanation' => [
            'title' => 'What IP address should I use?',
            'description' => 'The IP is the address from which you are trying to access your site',
            'what_is_ip' => 'What is an IP address?',
            'why_default' => 'By default we show your current IP, which is where you are browsing from now',
            'current_ip' => 'Your current IP: From this device and network',
            'problem_ip' => 'Problem IP: If the block is from another location, change it',
            'example' => 'Example: If you work from home but the block is at the office, use the office IP',
            'note' => '⚠️ Important: Use the IP from where you CANNOT access, not from where it DOES work',
        ],
        'ip_detection' => [
            'title' => 'How to know my IP address?',
            'current_device' => 'Automatic: We already detected your current IP',
            'how_to_find' => 'Manual: Visit {url} from the blocked device',
            'common_issues' => 'If you use VPN or proxy, the IP may change',
            'use_detected' => 'Use detected IP: {ip}',
        ],
        'more_info_wiki' => 'More information in the documentation',
    ],

    'bfm' => [
        'removed' => 'IP removed from DirectAdmin BFM blacklist',
        'whitelist_added' => 'IP added to DirectAdmin BFM whitelist',
        'warning_message' => 'DirectAdmin BFM had this IP in its internal blacklist. It has been removed and added to the whitelist for the configured grace period.',
        'removal_failed' => 'Failed to remove IP from DirectAdmin BFM blacklist',
    ],

    // Abuse Incidents Resource
    'abuse_incidents' => [
        'navigation_label' => 'Abuse Incidents',
        'navigation_group' => 'Simple Unblock Security',
        'singular' => 'Abuse Incident',
        'plural' => 'Abuse Incidents',

        // Sections
        'incident_information' => 'Incident Information',
        'target_information' => 'Target Information',
        'details' => 'Details',
        'resolution' => 'Resolution',

        // Fields
        'incident_type' => 'Incident Type',
        'severity' => 'Severity',
        'ip_address' => 'IP Address',
        'email_hash' => 'Email Hash',
        'email_hash_helper' => 'SHA-256 hash (GDPR compliant)',
        'domain' => 'Domain',
        'description' => 'Description',
        'metadata' => 'Metadata (JSON)',
        'resolved_at' => 'Resolved At',
        'occurred_at' => 'Occurred At',

        // Incident Types
        'types' => [
            'rate_limit_exceeded' => 'Rate Limit Exceeded',
            'ip_spoofing_attempt' => 'IP Spoofing Attempt',
            'otp_bruteforce' => 'OTP Brute Force',
            'honeypot_triggered' => 'Honeypot Triggered',
            'invalid_otp_attempts' => 'Invalid OTP Attempts',
            'ip_mismatch' => 'IP Mismatch',
            'suspicious_pattern' => 'Suspicious Pattern',
            'other' => 'Other',
        ],

        // Severity Levels
        'severity_levels' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],

        // Table Columns
        'type' => 'Type',
        'ip' => 'IP',
        'resolved' => 'Resolved',

        // Filters
        'status' => 'Status',
        'all_incidents' => 'All incidents',
        'resolved' => 'Resolved',
        'unresolved' => 'Unresolved',
        'from' => 'From',
        'until' => 'Until',

        // Actions
        'resolve' => 'Resolve',
        'unresolve' => 'Unresolve',
        'resolve_heading' => 'Resolve Incident',
        'resolve_description' => 'Mark this incident as resolved?',
        'mark_as_resolved' => 'Mark as Resolved',

        // Notifications
        'incident_resolved' => 'Incident resolved',
        'incident_unresolved' => 'Incident marked as unresolved',
        'incidents_resolved' => 'Incidents resolved',
    ],

    // IP Reputation Resource
    'ip_reputation' => [
        'navigation_label' => 'IP Reputation',
        'navigation_group' => 'Simple Unblock Security',
        'singular' => 'IP Reputation',
        'plural' => 'IP Reputation',

        // Sections
        'ip_information' => 'IP Information',
        'reputation_statistics' => 'Reputation & Statistics',
        'notes' => 'Notes',

        // Fields
        'ip_address' => 'IP Address',
        'subnet' => 'Subnet',
        'reputation_score' => 'Reputation Score',
        'total_requests' => 'Total Requests',
        'failed_requests' => 'Failed Requests',
        'blocked_count' => 'Blocked Count',
        'last_seen' => 'Last Seen',
        'first_seen' => 'First Seen',
        'admin_notes' => 'Admin Notes',
        'admin_notes_helper' => 'Add investigation notes or context about this IP address.',

        // Table Columns
        'score' => 'Score',
        'total' => 'Total',
        'failed' => 'Failed',
        'blocked' => 'Blocked',
        'success_rate' => 'Success %',

        // Filters
        'reputation' => 'Reputation',
        'high_reputation' => 'High (80-100)',
        'medium_reputation' => 'Medium (50-79)',
        'low_reputation' => 'Low (0-49)',
        'subnet_search' => 'Subnet Search',
        'from' => 'From',
        'until' => 'Until',

        // Actions
        'incidents' => 'Incidents',
    ],

    // Email Reputation Resource
    'email_reputation' => [
        'navigation_label' => 'Email Reputation',
        'navigation_group' => 'Simple Unblock Security',
        'singular' => 'Email Reputation',
        'plural' => 'Email Reputation',

        // Sections
        'email_information' => 'Email Information',
        'reputation_statistics' => 'Reputation & Statistics',
        'notes' => 'Notes',

        // Fields
        'email_hash' => 'Email Hash (SHA-256)',
        'email_hash_helper' => 'GDPR compliant - stores hash, not plaintext',
        'email_domain' => 'Email Domain',
        'reputation_score' => 'Reputation Score',
        'total_requests' => 'Total Requests',
        'verified_requests' => 'Verified Requests',
        'failed_requests' => 'Failed Requests',
        'last_seen' => 'Last Seen',
        'first_seen' => 'First Seen',
        'admin_notes' => 'Admin Notes',
        'admin_notes_helper' => 'Add investigation notes or context about this email address.',

        // Table Columns
        'domain' => 'Domain',
        'score' => 'Score',
        'total' => 'Total',
        'verified' => 'Verified',
        'failed' => 'Failed',
        'verification_rate' => 'Verification %',

        // Filters
        'reputation' => 'Reputation',
        'high_reputation' => 'High (80-100)',
        'medium_reputation' => 'Medium (50-79)',
        'low_reputation' => 'Low (0-49)',
        'from' => 'From',
        'until' => 'Until',

        // Actions
        'incidents' => 'Incidents',

        // Messages
        'hash_copied' => 'Full hash copied!',
    ],

    // Reports Resource
    'reports' => [
        'navigation_label' => 'Firewall Reports',
        'title' => 'Firewall Reports',
        'user' => 'User',
        'host' => 'Host',
        'ip' => 'IP',
        'created_at' => 'Created At',
        'last_read' => 'Last Read',
        'unassigned' => 'Unassigned',
        'logs' => 'Logs',
        'analysis' => 'Analysis',
        'created_from' => 'From',
        'created_until' => 'Until',
        'empty_state' => 'No firewall reports yet.',
    ],

    // Email Notifications
    'email' => [
        'block_origin_title' => 'Block Origin',
        'actions_taken' => 'Actions Taken',
        'action_csf_remove' => 'IP removed from CSF deny list',
        'action_csf_whitelist' => 'IP added to CSF temporary whitelist',
        'action_bfm_remove' => 'IP removed from DirectAdmin BFM blacklist',
        'action_mail_remove' => 'Mail logs processed',
        'action_web_remove' => 'Web logs processed',
        'technical_details' => 'Technical Details',
        'analysis_title' => 'Analysis Summary',
        'was_blocked' => 'Was Blocked?',
        'yes' => 'Yes',
        'no' => 'No',
        'unblock_performed' => 'Unblock Performed',
        'analysis_timestamp' => 'Analysis Date and Time',
        'web_report' => 'Full Web Report',
        'web_report_available' => 'You can view the full report online:',
        'web_report_link' => 'View full report',
    ],

    // Log Descriptions
    'logs' => [
        'descriptions' => [
            'csf' => [
                'title' => 'ConfigServer Firewall (CSF)',
                'description' => 'Main server firewall system',
                'wiki_link' => 'https://docs.configserver.com/csf/',
            ],
            'csf_deny' => [
                'title' => 'CSF Deny List',
                'description' => 'IPs permanently blocked by CSF',
            ],
            'csf_tempip' => [
                'title' => 'CSF Temporary List',
                'description' => 'IPs temporarily blocked by CSF',
            ],
            'bfm' => [
                'title' => 'DirectAdmin Brute Force Monitor (BFM)',
                'description' => 'DirectAdmin brute force attempt monitor',
            ],
            'mod_security' => [
                'title' => 'ModSecurity - Web Application Firewall',
                'description' => 'Web application firewall that detects and blocks attacks',
                'wiki_link' => 'https://modsecurity.org/about.html',
            ],
            'exim' => [
                'title' => 'Exim Logs (SMTP)',
                'description' => 'Outgoing mail server - Authentication logs and failed attempts',
            ],
            'exim_cpanel' => [
                'title' => 'Exim Logs (cPanel)',
                'description' => 'Outgoing mail server - Authentication logs and failed attempts',
            ],
            'exim_directadmin' => [
                'title' => 'Exim Logs (DirectAdmin)',
                'description' => 'Outgoing mail server - Authentication logs and failed attempts',
            ],
            'dovecot' => [
                'title' => 'Dovecot Logs (IMAP/POP3)',
                'description' => 'Incoming mail server - Authentication logs and failed attempts',
            ],
            'dovecot_cpanel' => [
                'title' => 'Dovecot Logs (cPanel)',
                'description' => 'Incoming mail server - Authentication logs and failed attempts',
            ],
            'dovecot_directadmin' => [
                'title' => 'Dovecot Logs (DirectAdmin)',
                'description' => 'Incoming mail server - Authentication logs and failed attempts',
            ],
        ],
    ],

    // Copy Report
    'copy_report' => [
        'title' => 'Send report copy',
        'optional' => '(optional)',
        'description' => 'You can send a copy of the report to another user in your account',
        'search_placeholder' => 'Search user by name or email...',
        'no_users_found' => 'No users found',
    ],
];
