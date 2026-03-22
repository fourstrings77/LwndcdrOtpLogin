<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Otp;

enum OtpMethod: string
{
    case Totp = 'totp';
    case Email = 'email';
}
