<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class MediaAssetController extends Controller
{
    public function index()
    {
        return view('admin.media-assets.index');
    }
}