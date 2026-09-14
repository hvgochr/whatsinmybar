<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Abuse\AbuseLimiter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AbuseProtectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AbuseLimiter $limiter, private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['protectAuthentication', 10], ['protectWrites', 6]]];
    }

    public function protectAuthentication(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || 'POST' !== $request->getMethod()) {
            return;
        }
        $ip = $request->getClientIp() ?? 'unknown';
        if ('/api/auth/register' === $request->getPathInfo()) {
            $this->limiter->consume(['registration' => $ip]);
        } elseif ('/api/auth/login' === $request->getPathInfo()) {
            // Bound IP work before parsing JSON; malformed login attempts count too.
            $this->limiter->consume(['login_ip' => $ip]);
            $payload = json_decode($request->getContent(), true);
            $email = is_array($payload) && is_string($payload['email'] ?? null) ? mb_strtolower(trim($payload['email'])) : '';
            $this->limiter->consume(['login_account' => $ip."\0".$email]);
        }
    }

    public function protectWrites(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/') || str_starts_with($path, '/api/auth/')) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $identity = (string) $user->getId();
        $budgets = ['writes' => $identity];
        $policy = match (true) {
            '/api/me/avatar' === $path, 1 === preg_match('#^/api/recipes/[^/]+/image$#D', $path) => 'uploads',
            1 === preg_match('#^/api/(comments(?:/|$)|recipes/[^/]+/comments$)#D', $path) => 'comments',
            '/api/reports' === $path => 'reports',
            1 === preg_match('#^/api/(recipes(?:/|$)|recipe_steps(?:/|$)|recipe_ingredients(?:/|$)|admin/recipes(?:/|$))#D', $path) => 'recipes',
            default => null,
        };
        if (null !== $policy) {
            $budgets[$policy] = $identity;
        }
        $this->limiter->consume($budgets);
    }
}
