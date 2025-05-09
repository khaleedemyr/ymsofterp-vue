<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Supplier;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('contact_person', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $suppliers = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:suppliers,code',
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'npwp' => 'nullable|string|max:30',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'payment_term' => 'nullable|string|max:50',
            'payment_days' => 'nullable|integer',
            'status' => 'required|in:active,inactive',
        ]);
        $supplier = Supplier::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'suppliers',
            'description' => 'Menambahkan supplier baru: ' . $supplier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $supplier->toArray(),
        ]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:suppliers,code,' . $id,
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'npwp' => 'nullable|string|max:30',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'payment_term' => 'nullable|string|max:50',
            'payment_days' => 'nullable|integer',
            'status' => 'required|in:active,inactive',
        ]);
        $supplier = Supplier::findOrFail($id);
        $oldData = $supplier->toArray();
        $supplier->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'suppliers',
            'description' => 'Mengupdate supplier: ' . $supplier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $supplier->fresh()->toArray(),
        ]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil diupdate!');
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $oldData = $supplier->toArray();
        $supplier->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'suppliers',
            'description' => 'Menonaktifkan supplier: ' . $supplier->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $supplier->fresh()->toArray(),
        ]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }
} 