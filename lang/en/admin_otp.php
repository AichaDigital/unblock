<?php

return [
    // Admin OTP Verification
    'title' => 'Administrator Verification',
    'subtitle' => 'For security, verify your identity with the code sent to your email',

    'code_label' => 'Verification Code',
    'code_help' => 'Enter the 6-digit code we sent to your email',

    'verify_button' => 'Verify',
    'verifying' => 'Verifying...',
    'resend_button' => 'Resend Code',
    'resend_wait' => 'Wait to resend',
    'cancel_button' => 'Cancel and Logout',

    // Messages
    'otp_sent' => 'Verification code sent to your email',
    'otp_resent' => 'Code resent successfully',
    'verification_success' => 'Verification successful! Redirecting...',
    'verification_error' => 'Error verifying code. Please try again.',
    'resend_error' => 'Error resending code. Please try again.',

    // Rate limiting
    'too_many_attempts' => 'Too many attempts. Please wait :seconds seconds.',
    'resend_too_many' => 'You have resent the code too many times. Wait :seconds seconds.',

    // Help
    'help_text' => 'Didn\'t receive the code? Check your spam folder or resend it.',

    // Session
    'session_expired' => 'Your session has expired for security. Please log in again with your password and verify the new OTP code.',
    'session_invalid' => 'Invalid session detected. Please log in again.',
];
