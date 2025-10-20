<?php

namespace App\Enums;

enum AuditAction: int
{
    case Email = 0;
    case Access = 1;
    case Clean = 2;
    case Success = 3;
    case TooManyRequests = 4;
    case OtpLogin = 5;

    public static function getValueByKey(string $key): int
    {
        return match ($key) {
            'email' => self::Email->value,
            'access' => self::Access->value,
            'clean' => self::Clean->value,
            'success' => self::Success->value,
            'too_many_requests' => self::TooManyRequests->value,
            'otp_login' => self::OtpLogin->value,
        };
    }
}
