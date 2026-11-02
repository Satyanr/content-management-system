<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeviceLifecycle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    private function forbidden(): array
    {
        return ['company_id' => ['prohibited'], 'device_id' => ['prohibited'], 'public_id' => ['prohibited'], 'device_public_id' => ['prohibited'], 'token' => ['prohibited'], 'credential' => ['prohibited']];
    }

    public function activate(Request $request, DeviceLifecycle $service)
    {
        $data = $request->validate($this->forbidden() + [
            'activation_code' => ['required', 'string', 'max:24', 'regex:/^[a-fA-F0-9-]{20,24}$/D'],
            'platform' => ['required', 'string', Rule::in(['windows', 'android', 'linux', 'other'])],
            'player_version' => ['nullable', 'string', 'max:50'],
            'screen_width' => ['required', 'integer', 'between:1,16384'], 'screen_height' => ['required', 'integer', 'between:1,16384'],
        ]);
        $result = $service->activate($data);

        return $result ? response()->json($result, 201)->header('Cache-Control', 'private, no-store') : response()->json(['message' => 'Activation code is invalid or unavailable.'], 422);
    }

    public function heartbeat(Request $request, DeviceLifecycle $service)
    {
        $data = $request->validate($this->forbidden() + [
            'platform' => ['sometimes', 'required', 'string', Rule::in(['windows', 'android', 'linux', 'other'])],
            'player_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'screen_width' => ['required_with:screen_height', 'integer', 'between:1,16384'], 'screen_height' => ['required_with:screen_width', 'integer', 'between:1,16384'],
            'uptime_seconds' => ['sometimes', 'integer', 'between:0,315360000'],
            'device_timestamp' => ['sometimes', 'string', 'max:40', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D'],
        ]);
        $credential = $request->attributes->get('device_credential');
        if (! $credential) {
            return response()->json(['message' => 'Invalid device credential.'], 401);
        }
        $result = $service->heartbeat($credential->id, $data);

        return $result ? response()->json($result) : response()->json(['message' => 'Invalid device credential.'], 401);
    }
}
