<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Converts any exception raised on an API request into the standard error envelope.
 *
 * Registered once in bootstrap/app.php so controllers never format errors themselves.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'VALIDATION_ERROR',
                'Validation failed',
                422,
                $e->errors(),
            ),

            $e instanceof AuthenticationException => ApiResponse::error(
                'UNAUTHENTICATED',
                'Authentication required.',
                401,
            ),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::error(
                'FORBIDDEN',
                'This action is unauthorized.',
                403,
            ),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error(
                'NOT_FOUND',
                'Resource not found.',
                404,
            ),

            $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                'METHOD_NOT_ALLOWED',
                'Method not allowed.',
                405,
            ),

            $e instanceof ThrottleRequestsException => ApiResponse::error(
                'TOO_MANY_REQUESTS',
                'Too many requests. Please try again later.',
                429,
            ),

            $e instanceof HttpExceptionInterface => ApiResponse::error(
                self::codeForStatus($e->getStatusCode()),
                $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                $e->getStatusCode(),
            ),

            default => self::serverError($e),
        };
    }

    private static function serverError(Throwable $e): JsonResponse
    {
        $debug = (bool) config('app.debug');

        return ApiResponse::error(
            'SERVER_ERROR',
            $debug ? $e->getMessage() : 'An unexpected error occurred.',
            500,
        );
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'HTTP_ERROR',
        };
    }
}
