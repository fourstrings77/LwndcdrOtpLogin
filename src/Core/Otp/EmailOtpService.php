<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Otp;

use Lwndcdr\OtpLogin\Core\Event\OtpCodeRequestedEvent;
use Lwndcdr\OtpLogin\Entity\CustomerOtp\CustomerOtpEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Generates, persists, sends and verifies Email OTP codes.
 *
 * Security guarantees:
 *  - Codes are hashed with password_hash(PASSWORD_BCRYPT) before storage.
 *  - Codes expire after a configurable TTL (default 10 minutes).
 *  - Codes are single-use: invalidated immediately after successful verification.
 *
 * The actual mail is sent via Shopware's flow system (event: OtpCodeRequestedEvent).
 * The default flow and template are created by Migration1774080453AddOtpMailTemplate.
 */
class EmailOtpService
{
    private const CODE_LENGTH = 6;
    private const CONFIG_KEY_TTL = 'OtpLogin.config.emailOtpTtlMinutes';

    public function __construct(
        private readonly EntityRepository $customerOtpRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
    ) {}

    /**
     * Generate and send a new OTP code to the customer's email address.
     * The hashed code and expiry are written to the CustomerOtp record,
     * then an OtpCodeRequestedEvent is dispatched for the flow to handle sending.
     */
    public function sendCode(
        CustomerEntity $customer,
        CustomerOtpEntity $customerOtp,
        SalesChannelContext $context,
    ): void {
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        $ttlMinutes = (int) ($this->systemConfigService->get(self::CONFIG_KEY_TTL) ?? 10);
        $expiresAt = new \DateTimeImmutable('+' . $ttlMinutes . ' minutes');

        $this->customerOtpRepository->update([[
            'id' => $customerOtp->getId(),
            'emailOtpCode' => password_hash($code, PASSWORD_BCRYPT),
            'emailOtpExpiresAt' => $expiresAt,
        ]], $context->getContext());

        $this->eventDispatcher->dispatch(
            new OtpCodeRequestedEvent($context, $customer, $code, $ttlMinutes),
            OtpCodeRequestedEvent::EVENT_NAME,
        );
    }

    /**
     * Verify a submitted code against the stored hash.
     * Returns false if the code is expired, missing, or incorrect.
     */
    public function verifyCode(CustomerOtpEntity $customerOtp, string $code): bool
    {
        if ($customerOtp->getEmailOtpCode() === null) {
            return false;
        }

        if ($customerOtp->getEmailOtpExpiresAt() !== null
            && $customerOtp->getEmailOtpExpiresAt() < new \DateTimeImmutable()
        ) {
            return false;
        }

        return password_verify($code, $customerOtp->getEmailOtpCode());
    }

    /**
     * Invalidate the stored code after successful verification (single-use).
     */
    public function invalidateCode(CustomerOtpEntity $customerOtp, \Shopware\Core\Framework\Context $context): void
    {
        $this->customerOtpRepository->update([[
            'id' => $customerOtp->getId(),
            'emailOtpCode' => null,
            'emailOtpExpiresAt' => null,
        ]], $context);
    }
}
