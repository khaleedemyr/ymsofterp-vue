<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenancePriorityController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('maintenance_priorities')->get());
    }
}
