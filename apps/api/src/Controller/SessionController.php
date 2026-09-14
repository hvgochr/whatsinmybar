<?php

namespace App\Controller;

use App\Service\RefreshCookie;
use App\Service\RefreshSession;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class SessionController
{
    public function __construct(private RefreshSession $sessions, private RefreshCookie $cookies)
    {
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $session = $this->sessions->rotate($request->cookies->get('refresh_token', ''));
        } catch (DriverException $error) {
            if (!$error instanceof RetryableException && '55P03' !== $error->getSQLState()) {
                throw $error;
            }

            return new JsonResponse(['error' => ['status' => 503, 'code' => 'session_unavailable', 'message' => 'Please retry shortly.']], 503, ['Retry-After' => '2']);
        }

        if (null === $session) {
            // A late failure must never overwrite another request's newer cookie.
            return new JsonResponse(['error' => ['status' => 401, 'code' => 'unauthorized', 'message' => 'Authentication required.']], 401);
        }
        $response = new JsonResponse(['token' => $session['token']]);
        $response->headers->setCookie($this->cookies->create($session['refresh'], $session['expires']));

        return $response;
    }

    public function logout(Request $request): JsonResponse
    {
        $this->sessions->revoke($request->cookies->get('refresh_token', ''));
        $response = new JsonResponse(['message' => 'Logged out.']);
        $response->headers->setCookie($this->cookies->create());

        return $response;
    }
}
