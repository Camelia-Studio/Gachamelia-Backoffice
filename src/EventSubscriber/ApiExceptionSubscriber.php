<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isApiRequest($event->getRequest())) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = [];

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $headers = $exception->getHeaders();
        } elseif ($exception instanceof AccessDeniedException) {
            $statusCode = Response::HTTP_FORBIDDEN;
        }

        $statusText = Response::$statusTexts[$statusCode] ?? 'Error';

        $event->setResponse(new JsonResponse(
            [
                'error' => $this->normalizeErrorCode($statusText),
                'message' => $statusText,
                'status' => $statusCode,
            ],
            $statusCode,
            $headers,
        ));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    private function isApiRequest(Request $request): bool
    {
        $pathInfo = $request->getPathInfo();

        return '/api' === $pathInfo || str_starts_with($pathInfo, '/api/');
    }

    private function normalizeErrorCode(string $statusText): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($statusText)));
    }
}
