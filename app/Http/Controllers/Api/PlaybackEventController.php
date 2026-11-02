<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlaybackEventIngestion;
use Illuminate\Http\Request;

class PlaybackEventController extends Controller
{
    public function store(Request $request, PlaybackEventIngestion $service)
    {
        return response()->json($service->ingest($request->attributes->get('device_credential')->device, $request->all()));
    }

    public function state(Request $request, PlaybackEventIngestion $service)
    {
        return response()->json($service->state($request->attributes->get('device_credential')->device, $request->all()));
    }
}
