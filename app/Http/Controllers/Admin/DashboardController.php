<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SignageDashboard;

class DashboardController extends Controller
{
    public function index(SignageDashboard $dashboard)
    {
        return view('admin.dashboard.index', $dashboard->data());
    }
}
