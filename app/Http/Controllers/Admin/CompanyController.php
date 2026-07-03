<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class CompanyController extends Controller
{
    public function index()
    {
        return view('admin.companies.index');
    }
}