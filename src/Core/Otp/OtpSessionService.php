<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Otp;

use Symfony\Component\HttpFoundation\Request;

/**
 * Manages the otp_pending session state during the two-step login flow.
 *
 * While a session has an active OTP challenge, every storefront request
 * (except /otp/verify and /account/logout) is intercepted and redirected
 * to the verification page. Clearing the state grants full access.
 */
class OtpSessionService
{
    private const SESSION_PENDING = 'lwndcdr_otp_pending';
    private const SESSION_CUSTOMER_ID = 'lwndcdr_otp_pending_customer_id';
    private const SESSION_METHOD = 'lwndcdr_otp_pending_method';

    public function initiate(Request $request, string $customerId, OtpMethod $method): void
    {
        $session = $request->getSession();
        $session->set(self::SESSION_PENDING, true);
        $session->set(self::SESSION_CUSTOMER_ID, $customerId);
        $session->set(self::SESSION_METHOD, $method->value);
    }

    public function isPending(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        return (bool) $request->getSession()->get(self::SESSION_PENDING, false);
    }

    public function getPendingCustomerId(Request $request): ?string
    {
        return $request->getSession()->get(self::SESSION_CUSTOMER_ID);
    }

    public function getPendingMethod(Request $request): ?OtpMethod
    {
        $value = $request->getSession()->get(self::SESSION_METHOD);

        return $value !== null ? OtpMethod::from($value) : null;
    }

    public function clear(Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_PENDING);
        $session->remove(self::SESSION_CUSTOMER_ID);
        $session->remove(self::SESSION_METHOD);
    }
}
