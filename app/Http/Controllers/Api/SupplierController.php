<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);
        return response()->json($supplier);
    }

    public function index(Request $request)
    {
        $q = $request->input('q');
        $query = Supplier::query()->where('status', 'active');
        if ($q) {
            $query->where('name', 'like', "%$q%") ;
        }
        return $query->get(['id', 'name']);
    }
}
