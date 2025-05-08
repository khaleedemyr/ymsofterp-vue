<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\ActivityLog;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('customers');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('code', 'like', "%$search%")
                  ->orWhere('region', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $customers = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:customers,code',
            'name' => 'required|string|max:100',
            'type' => 'required|in:branch,customer',
            'region' => 'required|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);
        $id = DB::table('customers')->insertGetId([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'region' => $validated['region'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customer = DB::table('customers')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'customers',
            'description' => 'Menambahkan customer: ' . $customer->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => json_encode($customer),
        ]);
        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:customers,code,' . $id,
            'name' => 'required|string|max:100',
            'type' => 'required|in:branch,customer',
            'region' => 'required|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);
        $customer = DB::table('customers')->where('id', $id)->first();
        $oldData = $customer;
        DB::table('customers')->where('id', $id)->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'region' => $validated['region'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);
        $newData = DB::table('customers')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'customers',
            'description' => 'Mengupdate customer: ' . $newData->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('customers.index')->with('success', 'Customer berhasil diupdate!');
    }

    public function destroy($id)
    {
        $customer = DB::table('customers')->where('id', $id)->first();
        $oldData = $customer;
        DB::table('customers')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        $newData = DB::table('customers')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'customers',
            'description' => 'Menonaktifkan customer: ' . $customer->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return redirect()->route('customers.index')->with('success', 'Customer berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $customer = DB::table('customers')->where('id', $id)->first();
        $oldData = $customer;
        DB::table('customers')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        $newData = DB::table('customers')->where('id', $id)->first();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'status_toggle',
            'module' => 'customers',
            'description' => 'Mengubah status customer: ' . $customer->name . ' menjadi ' . $request->status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
        ]);
        return response()->json(['success' => true]);
    }
} 