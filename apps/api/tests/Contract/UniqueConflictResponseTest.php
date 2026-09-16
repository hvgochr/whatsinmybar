<?php

namespace App\Tests\Contract;

use App\EventSubscriber\ApiExceptionSubscriber;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class UniqueConflictResponseTest extends TestCase
{
    public function testConcurrentUniqueViolationUsesControlledConflictResponse(): void
    {
        $driverException = new class('duplicate key value violates unique constraint "uniq_user_email"') extends \RuntimeException implements DriverException {
            public function getSQLState(): string
            {
                return '23505';
            }
        };
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/auth/register', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new UniqueConstraintViolationException($driverException, null),
        );

        (new ApiExceptionSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame([
            'error' => [
                'status' => 409,
                'code' => 'conflict',
                'message' => 'Email is already in use.',
            ],
        ], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
