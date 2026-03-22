<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Subscriber;

use Lwndcdr\OtpLogin\Core\Otp\OtpService;
use Lwndcdr\OtpLogin\Core\Otp\OtpSessionService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Two responsibilities:
 *
 * 1. After login: checks whether the customer has OTP enabled and initiates
 *    the challenge (sets the otp_pending session flag).
 *
 * 2. On every storefront request: if otp_pending is active, redirects to
 *    /otp/verify — except for the verify routes and logout.
 */
class CustomerLoginSubscriber implements EventSubscriberInterface
{
    /** Routes that are reachable while otp_pending is set. */
    private const OTP_BYPASS_ROUTES = [
        'frontend.otp.verify.page',
        'frontend.otp.verify',
        'frontend.account.logout.page',
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OtpService $otpService,
        private readonly OtpSessionService $sessionService,
        private readonly RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }

    /**
     * Intercept login: initiate OTP challenge when the customer has any method
     * enabled. Errors during challenge initiation (e.g. mail send failure) must
     * not break the login — they are silently swallowed so the user can still
     * log in without OTP.
     */
    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return;
        }

        try {
            $this->otpService->initiateChallengeIfRequired(
                $request,
                $event->getCustomer(),
                $event->getSalesChannelContext(),
            );
        } catch (\Throwable) {
            // Do not break the login flow on OTP service failure.
        }
    }

    /**
     * Guard all storefront requests: redirect to the OTP verification page
     * whenever an unresolved OTP challenge is present in the session.
     *
     * Runs after Symfony's RouterListener (priority 32) so _route is available.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->sessionService->isPending($request)) {
            return;
        }

        // Skip ESI includes and other internal paths (/_esi/, /_wdt/, /_profiler/, …).
        // These are real HTTP requests made by the cache layer that must never be redirected.
        if (str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        // Skip XHR/AJAX requests. Storefront JS makes widget/fragment calls that must not
        // be redirected — they'd follow the 302 and hit the page-controller XHR guard.
        // Full-page navigations (where the guard actually matters) are never XHR.
        if ($request->isXmlHttpRequest()) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');

        // Allow bypass routes and internal Symfony routes (_profiler, _wdt, …).
        if (in_array($route, self::OTP_BYPASS_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }

        $event->setResponse(
            new RedirectResponse($this->router->generate('frontend.otp.verify.page'))
        );
    }
}
