<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class RefreshCookie
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        #[Autowire('%gesdinet_jwt_refresh_token.cookie%')]
        private array $settings,
    ) {
    }

    public function create(?string $value = null, int $expires = 1): Cookie
    {
        return Cookie::create('refresh_token', $value, $expires, '/', null, (bool) $this->settings['secure'], true, false, Cookie::SAMESITE_STRICT);
    }
}
