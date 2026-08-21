<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class RefreshTokenCsrfProtectionSubscriber implements EventSubscriberInterface
{
    private const PROTECTED_PATHS = [
        '/api/auth/logout',
        '/api/auth/refresh',
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['protectCookieAuthenticatedRequest', 8]];
    }

    public function protectCookieAuthenticatedRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod() || !in_array($request->getPathInfo(), self::PROTECTED_PATHS, true)) {
            return;
        }

        if ('1' !== $request->headers->get('X-CSRF-Protection')) {
            throw new AccessDeniedHttpException('CSRF protection header is required.');
        }
    }
}
