<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isApiRequest($request)) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof UniqueConstraintViolationException) {
            $event->setResponse(new JsonResponse([
                'error' => [
                    'status' => Response::HTTP_CONFLICT,
                    'code' => 'conflict',
                    'message' => $this->uniqueConflictMessage($exception),
                ],
            ], Response::HTTP_CONFLICT));

            return;
        }

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $status = $exception->getStatusCode();
        $event->setResponse(new JsonResponse([
            'error' => [
                'status' => $status,
                'code' => $this->code($status),
                'message' => $this->message($exception, $status),
            ],
        ], $status, $exception->getHeaders()));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    private function code(int $status): string
    {
        $text = Response::$statusTexts[$status] ?? 'Error';

        return (string) preg_replace('/[^a-z0-9]+/', '_', trim(strtolower($text)));
    }

    private function message(HttpExceptionInterface $exception, int $status): string
    {
        if ($status >= 500) {
            return 'Unexpected server error.';
        }

        $message = trim($exception->getMessage());

        return '' === $message ? (Response::$statusTexts[$status] ?? 'Error') : $message;
    }

    private function uniqueConflictMessage(UniqueConstraintViolationException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'uniq_user_email') => 'Email is already in use.',
            str_contains($message, 'uniq_user_username') => 'Username is already in use.',
            str_contains($message, 'uniq_recipe_slug') => 'Recipe slug is already in use.',
            str_contains($message, 'uniq_category_slug') => 'Category slug is already in use.',
            str_contains($message, 'uniq_ingredient_slug') => 'Ingredient slug is already in use.',
            default => 'A resource with the same unique value already exists.',
        };
    }
}
