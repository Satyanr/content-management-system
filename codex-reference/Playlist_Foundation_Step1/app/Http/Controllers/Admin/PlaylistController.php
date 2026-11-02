<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PlaylistController extends Controller
{
    public function index()
    {
        return view('admin.playlists.index');
    }
}
