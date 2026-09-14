<?php

namespace App\EventSubscriber;

use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeStep;
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
        if ('api_auth_register' === $request->attributes->get('_route')) {
            $this->limiter->consume(['registration' => $ip]);
        } elseif ('api_auth_login' === $request->attributes->get('_route')) {
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
        $path = rawurldecode($request->getPathInfo());
        if (!str_starts_with($path, '/api/') || str_starts_with($path, '/api/auth/')) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $identity = (string) $user->getId();
        $budgets = ['writes' => $identity];
        $route = $request->attributes->get('_route');
        // Classify matched routes/resources, not raw URL strings: format suffixes
        // and percent-encoded paths must spend the same quota.
        $policy = match (true) {
            in_array($route, ['api_me_avatar_upload', 'api_recipe_image_upload', 'api_recipe_image_delete'], true) => 'uploads',
            in_array($route, ['api_recipe_comments_create', 'api_comments_update', 'api_comments_delete'], true) => 'comments',
            'api_reports_create' === $route => 'reports',
            in_array($request->attributes->get('_api_resource_class'), [Recipe::class, RecipeStep::class, RecipeIngredient::class], true),
            in_array($route, ['api_recipe_aggregate_create', 'api_recipe_aggregate_update', 'api_recipe_publish', 'api_recipe_archive', 'api_recipe_favorite_add', 'api_recipe_favorite_remove', 'api_admin_recipes_update'], true) => 'recipes',
            default => null,
        };
        if (null !== $policy) {
            $budgets[$policy] = $identity;
        }
        $this->limiter->consume($budgets);
    }
}
