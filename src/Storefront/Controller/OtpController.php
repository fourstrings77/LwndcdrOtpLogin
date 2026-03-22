<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Storefront\Controller;

use Lwndcdr\OtpLogin\Core\Otp\OtpService;
use Lwndcdr\OtpLogin\Core\Otp\OtpSessionService;
use Lwndcdr\OtpLogin\Core\Otp\TotpService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class OtpController extends StorefrontController
{
    private const SESSION_PENDING_TOTP_SECRET = 'lwndcdr_otp_pending_totp_secret';

    public function __construct(
        private readonly EntityRepository $customerOtpRepository,
        private readonly TotpService $totpService,
        private readonly OtpService $otpService,
        private readonly OtpSessionService $sessionService,
        private readonly RateLimiterFactory $otpVerifyLimiter,
    ) {}

    /**
     * Render the account security / OTP settings page.
     */
    #[Route(
        path: '/account/otp',
        name: 'frontend.account.otp.page',
        defaults: ['_loginRequired' => true],
        methods: ['GET'],
    )]
    public function accountOtpPage(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        $customerOtp = $this->findCustomerOtp($customer->getId(), $context->getContext());

        $templateVars = ['customerOtp' => $customerOtp];

        $pendingSecret = $request->getSession()->get(self::SESSION_PENDING_TOTP_SECRET);
        if ($pendingSecret !== null) {
            $provisioningUri = $this->totpService->buildProvisioningUri(
                plainSecret: $pendingSecret,
                label: $customer->getEmail(),
                issuer: 'OtpLogin',
            );

            $templateVars['totpQrCodeDataUri'] = $this->totpService->buildQrCodeDataUri($provisioningUri);
            $templateVars['totpPlainSecret'] = $pendingSecret;
        }

        return $this->renderStorefront(
            '@LwndcdrOtpLogin/storefront/otp/account-otp.html.twig',
            $templateVars,
        );
    }

    /**
     * Toggle Email OTP on or off.
     */
    #[Route(
        path: '/account/otp/email/toggle',
        name: 'frontend.account.otp.email.toggle',
        defaults: ['_loginRequired' => true],
        methods: ['POST'],
    )]
    public function toggleEmailOtp(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $customerId = $context->getCustomer()->getId();
        $customerOtp = $this->findCustomerOtp($customerId, $context->getContext());

        if ($customerOtp === null) {
            $this->customerOtpRepository->create([[
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'emailOtpEnabled' => true,
            ]], $context->getContext());
        } else {
            $this->customerOtpRepository->update([[
                'id' => $customerOtp->getId(),
                'emailOtpEnabled' => !$customerOtp->isEmailOtpEnabled(),
            ]], $context->getContext());
        }

        $this->addFlash(self::SUCCESS, $this->trans('lwndcdr-otp.account.emailOtp.toggleSuccess'));

        return $this->redirectToRoute('frontend.account.otp.page');
    }

    /**
     * Initiate TOTP setup: generate a new secret and store it in the session,
     * then redirect back to the page so the QR code is displayed.
     */
    #[Route(
        path: '/account/otp/totp/setup',
        name: 'frontend.account.otp.totp.setup',
        defaults: ['_loginRequired' => true],
        methods: ['POST'],
    )]
    public function initiateTotp(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $request->getSession()->set(
            self::SESSION_PENDING_TOTP_SECRET,
            $this->totpService->generateSecret(),
        );

        return $this->redirectToRoute('frontend.account.otp.page');
    }

    /**
     * Confirm the TOTP setup by verifying the submitted code against the pending
     * session secret, then persist the encrypted secret.
     */
    #[Route(
        path: '/account/otp/totp/confirm',
        name: 'frontend.account.otp.totp.confirm',
        defaults: ['_loginRequired' => true],
        methods: ['POST'],
    )]
    public function confirmTotp(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $pendingSecret = $request->getSession()->get(self::SESSION_PENDING_TOTP_SECRET);

        if ($pendingSecret === null) {
            return $this->redirectToRoute('frontend.account.otp.page');
        }

        $code = (string) $request->request->get('totp_code', '');

        if (!$this->totpService->verifyCode($pendingSecret, $code)) {
            $this->addFlash(self::DANGER, $this->trans('lwndcdr-otp.account.totp.invalidCode'));

            return $this->redirectToRoute('frontend.account.otp.page');
        }

        $customerId = $context->getCustomer()->getId();
        $customerOtp = $this->findCustomerOtp($customerId, $context->getContext());
        $encryptedSecret = $this->totpService->encryptSecret($pendingSecret);

        if ($customerOtp === null) {
            $this->customerOtpRepository->create([[
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'totpSecret' => $encryptedSecret,
                'totpEnabled' => true,
            ]], $context->getContext());
        } else {
            $this->customerOtpRepository->update([[
                'id' => $customerOtp->getId(),
                'totpSecret' => $encryptedSecret,
                'totpEnabled' => true,
            ]], $context->getContext());
        }

        $request->getSession()->remove(self::SESSION_PENDING_TOTP_SECRET);

        $this->addFlash(self::SUCCESS, $this->trans('lwndcdr-otp.account.totp.setupSuccess'));

        return $this->redirectToRoute('frontend.account.otp.page');
    }

    /**
     * Disable TOTP and wipe the stored secret.
     */
    #[Route(
        path: '/account/otp/totp/disable',
        name: 'frontend.account.otp.totp.disable',
        defaults: ['_loginRequired' => true],
        methods: ['POST'],
    )]
    public function disableTotp(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $customerOtp = $this->findCustomerOtp(
            $context->getCustomer()->getId(),
            $context->getContext(),
        );

        if ($customerOtp !== null) {
            $this->customerOtpRepository->update([[
                'id' => $customerOtp->getId(),
                'totpSecret' => null,
                'totpEnabled' => false,
            ]], $context->getContext());
        }

        $this->addFlash(self::SUCCESS, $this->trans('lwndcdr-otp.account.totp.disableSuccess'));

        return $this->redirectToRoute('frontend.account.otp.page');
    }

    /**
     * Render the OTP verification page shown after login.
     */
    #[Route(
        path: '/otp/verify',
        name: 'frontend.otp.verify.page',
        methods: ['GET'],
    )]
    public function verifyPage(Request $request, SalesChannelContext $context): Response
    {
        if (!$this->sessionService->isPending($request)) {
            return $this->redirectToRoute('frontend.home');
        }

        return $this->renderStorefront('@LwndcdrOtpLogin/storefront/otp/verify.html.twig');
    }

    /**
     * Handle OTP verification form submission.
     * Rate-limited to 5 attempts per 15 minutes per IP.
     */
    #[Route(
        path: '/otp/verify',
        name: 'frontend.otp.verify',
        methods: ['POST'],
    )]
    public function verify(Request $request, SalesChannelContext $context): Response
    {
        if (!$this->sessionService->isPending($request)) {
            return $this->redirectToRoute('frontend.home');
        }

        $limiter = $this->otpVerifyLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $this->addFlash(self::DANGER, $this->trans('lwndcdr-otp.verify.rateLimited'));

            return $this->renderStorefront('@LwndcdrOtpLogin/storefront/otp/verify.html.twig');
        }

        if (!$this->otpService->verify($request, $context)) {
            $this->addFlash(self::DANGER, $this->trans('lwndcdr-otp.verify.invalidCode'));

            return $this->renderStorefront('@LwndcdrOtpLogin/storefront/otp/verify.html.twig');
        }

        $limiter->reset();

        return $this->redirectToRoute('frontend.account.home.page');
    }

    private function findCustomerOtp(string $customerId, Context $context): ?\Lwndcdr\OtpLogin\Entity\CustomerOtp\CustomerOtpEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        return $this->customerOtpRepository->search($criteria, $context)->first();
    }
}
