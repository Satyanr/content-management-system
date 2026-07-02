<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
    public function index()
    {
        return view('admin.permissions.index');
    }
}