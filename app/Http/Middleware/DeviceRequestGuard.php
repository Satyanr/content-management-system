<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceRequestGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strlen($request->getContent()) > ($request->is('api/device/playback-events') ? 65536 : (int) config('devices.max_payload_bytes', 8192))) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }
        if ($request->query->count()) {
            return response()->json(['message' => 'Use a JSON body and Authorization header, not query parameters.'], 422);
        }
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            if (! in_array(trim($request->getContent()), ['', '{}', '[]'], true)) {
                return response()->json(['message' => 'Request body is not supported.'], 422);
            }

            return $next($request);
        }
        if (! $request->isJson()) {
            return response()->json(['message' => 'JSON content type is required.'], 415);
        }
        try {
            $json = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['message' => 'Malformed JSON body.'], 400);
        }
        if (! is_array($json)) {
            return response()->json(['message' => 'JSON object body is required.'], 422);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
