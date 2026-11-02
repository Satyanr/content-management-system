<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class DeviceApiContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        $request->attributes->set('device_request_id', $id);
        Log::withContext(['device_request_id' => $id]);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            report($exception);
            $response = self::exceptionResponse($exception, $request);
        }
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $data = $response->getData(true);
            $data['error'] ??= ['code' => 'invalid_request'];
            $data['requestId'] = $id;
            $response->setData($data);
        }
        $response->headers->set('X-Request-ID', $id);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Vary', 'Authorization, X-Player-Schema-Version');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        Log::withoutContext(['device_request_id']);

        return $response;
    }

    public static function exceptionResponse(Throwable $exception, Request $request): JsonResponse
    {
        $status = $exception instanceof ValidationException ? 422 : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
        $id = $request->attributes->get('device_request_id') ?? (string) Str::uuid();
        $response = response()->json(['message' => $status >= 500 ? 'Device service unavailable.' : 'Device request rejected.', 'error' => ['code' => match ($status) {
            401 => 'invalid_token', 404 => 'media_not_found', 409 => 'sync_revision_changed', 422 => 'validation_failed', 429 => 'rate_limited', default => 'request_failed',
        }], 'requestId' => $id], $status);
        if ($exception instanceof ValidationException) {
            $response->setData($response->getData(true) + ['errors' => $exception->errors()]);
        }
        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->add($exception->getHeaders());
        }
        $response->headers->set('X-Request-ID', $id);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
