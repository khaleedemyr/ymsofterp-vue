<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceLabelController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('maintenance_labels')->get());
    }
}
