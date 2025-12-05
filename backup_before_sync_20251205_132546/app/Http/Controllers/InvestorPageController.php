<?php

namespace App\Http\Controllers;

class InvestorPageController extends Controller
{
    public function index()
    {
        return inertia('Investor/Index');
    }
} 