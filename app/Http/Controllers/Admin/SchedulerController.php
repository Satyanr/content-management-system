<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SchedulerController extends Controller
{
    public function index()
    {
        return view('admin.scheduler.index');
    }
}
