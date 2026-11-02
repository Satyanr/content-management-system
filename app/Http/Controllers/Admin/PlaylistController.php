<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlaylistWorkspace;

class PlaylistController extends Controller
{
    public function studio(int $playlist, PlaylistWorkspace $workspace)
    {
        return view('admin.playlists.studio', ['playlist' => $workspace->find($playlist)]);
    }

    public function index()
    {
        return view('admin.playlists.index');
    }
}
