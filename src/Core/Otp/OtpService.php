<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Otp;

use Lwndcdr\OtpLogin\Entity\CustomerOtp\CustomerOtpEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Central business logic for OTP authentication.
 *
 * Decides whether a customer must complete an OTP challenge after login,
 * initiates the appropriate method, and verifies submitted codes.
 *
 * TOTP takes priority when both methods are active.
 */
class OtpService
{
    public function __construct(
        private readonly EntityRepository $customerOtpRepository,
        private readonly TotpService $totpService,
        private readonly EmailOtpService $emailOtpService,
        private readonly OtpSessionService $sessionService,
    ) {}

    /**
     * Called directly after a successful customer login.
     *
     * If the customer has any OTP method enabled, sets the otp_pending session
     * flag and (for Email OTP) sends the code. Returns true when a challenge
     * was initiated so the caller knows a redirect is needed.
     */
    public function initiateChallengeIfRequired(
        Request $request,
        CustomerEntity $customer,
        SalesChannelContext $context,
    ): bool {
        $customerOtp = $this->loadCustomerOtp($customer->getId(), $context);

        if ($customerOtp === null) {
            return false;
        }

        if ($customerOtp->isTotpEnabled()) {
            $this->sessionService->initiate($request, $customer->getId(), OtpMethod::Totp);

            return true;
        }

        if ($customerOtp->isEmailOtpEnabled()) {
            $this->emailOtpService->sendCode($customer, $customerOtp, $context);
            $this->sessionService->initiate($request, $customer->getId(), OtpMethod::Email);

            return true;
        }

        return false;
    }

    /**
     * Verify the code submitted on /otp/verify.
     *
     * On success the otp_pending session state is cleared.
     * Returns false on incorrect code, expired code, or no pending challenge.
     */
    public function verify(Request $request, SalesChannelContext $context): bool
    {
        $customerId = $this->sessionService->getPendingCustomerId($request);
        $method = $this->sessionService->getPendingMethod($request);

        if ($customerId === null || $method === null) {
            return false;
        }

        $customerOtp = $this->loadCustomerOtp($customerId, $context);

        if ($customerOtp === null) {
            return false;
        }

        $code = (string) $request->request->get('otp_code', '');

        $valid = match ($method) {
            OtpMethod::Totp => $this->verifyTotp($customerOtp, $code),
            OtpMethod::Email => $this->verifyEmail($customerOtp, $code, $context),
        };

        if ($valid) {
            $this->sessionService->clear($request);
        }

        return $valid;
    }

    private function verifyTotp(CustomerOtpEntity $customerOtp, string $code): bool
    {
        if ($customerOtp->getTotpSecret() === null) {
            return false;
        }

        $plainSecret = $this->totpService->decryptSecret($customerOtp->getTotpSecret());

        return $this->totpService->verifyCode($plainSecret, $code);
    }

    private function verifyEmail(
        CustomerOtpEntity $customerOtp,
        string $code,
        SalesChannelContext $context,
    ): bool {
        $valid = $this->emailOtpService->verifyCode($customerOtp, $code);

        if ($valid) {
            $this->emailOtpService->invalidateCode($customerOtp, $context->getContext());
        }

        return $valid;
    }

    private function loadCustomerOtp(string $customerId, SalesChannelContext $context): ?CustomerOtpEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        return $this->customerOtpRepository->search($criteria, $context->getContext())->first();
    }
}
