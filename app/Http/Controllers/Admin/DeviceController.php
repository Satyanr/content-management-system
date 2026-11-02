<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DeviceWorkspace;

class DeviceController extends Controller
{
    public function index()
    {
        return view('admin.devices.index');
    }

    public function show(string $publicId, DeviceWorkspace $workspace)
    {
        $device = Device::forCompany($workspace->company())->where('public_id', $publicId)->firstOrFail();

        return view('admin.devices.show', ['publicId' => $device->public_id]);
    }
}
