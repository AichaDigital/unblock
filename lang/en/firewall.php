<?php

return [
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
];
