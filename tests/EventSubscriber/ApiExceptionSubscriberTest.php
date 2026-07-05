<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ApiExceptionSubscriberTest extends TestCase
{
    public function testApiAccessDeniedExceptionReturnsForbiddenJson(): void
    {
        $event = $this->createExceptionEvent('/api/protected', new AccessDeniedException());

        (new ApiExceptionSubscriber())->onKernelException($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertSame(
            [
                'error' => 'forbidden',
                'message' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
            ],
            json_decode($response->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testApiRuntimeExceptionReturnsInternalServerErrorJson(): void
    {
        $event = $this->createExceptionEvent('/api/protected', new \RuntimeException('Sensitive failure detail.'));

        (new ApiExceptionSubscriber())->onKernelException($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame(
            [
                'error' => 'internal_server_error',
                'message' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            json_decode($response->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testNonApiExceptionIsIgnored(): void
    {
        $event = $this->createExceptionEvent('/unknown', new \RuntimeException());

        (new ApiExceptionSubscriber())->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function createExceptionEvent(string $path, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            new class implements HttpKernelInterface {
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            },
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
