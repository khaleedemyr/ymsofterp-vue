<?php

namespace App\Http\Controllers;

use App\Models\Divisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DivisiController extends Controller
{
    public function index(Request $request)
    {
        $query = Divisi::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('nama_divisi', 'like', "%{$search}%");
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }
        } else {
            // Default to active only
            $query->active();
        }

        $divisis = $query->orderBy('nama_divisi')->paginate(10);

        // Transform agar accessor ikut terkirim
        $divisis->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'nama_divisi' => $item->nama_divisi,
                'nominal_lembur' => $item->nominal_lembur,
                'nominal_uang_makan' => $item->nominal_uang_makan,
                'nominal_ph' => $item->nominal_ph,
                'status' => $item->status,
                'formatted_nominal_lembur' => $item->formatted_nominal_lembur,
                'formatted_nominal_uang_makan' => $item->formatted_nominal_uang_makan,
                'formatted_nominal_ph' => $item->formatted_nominal_ph,
            ];
        });

        return Inertia::render('Divisi/Index', [
            'divisis' => $divisis,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make([
            'nama_divisi' => $request->nama_divisi,
            'nominal_lembur' => $request->nominal_lembur,
            'nominal_uang_makan' => $request->nominal_uang_makan,
            'nominal_ph' => $request->nominal_ph,
        ], [
            'nama_divisi' => 'required|string|max:255|unique:tbl_data_divisi,nama_divisi',
            'nominal_lembur' => 'required|integer|min:0',
            'nominal_uang_makan' => 'required|integer|min:0',
            'nominal_ph' => 'nullable|integer|min:0',
        ], [
            'nama_divisi.required' => 'Nama divisi wajib diisi',
            'nama_divisi.unique' => 'Nama divisi sudah ada',
            'nominal_lembur.required' => 'Nominal lembur wajib diisi',
            'nominal_lembur.integer' => 'Nominal lembur harus berupa angka',
            'nominal_uang_makan.required' => 'Nominal uang makan wajib diisi',
            'nominal_uang_makan.integer' => 'Nominal uang makan harus berupa angka',
            'nominal_ph.integer' => 'Nominal PH harus berupa angka',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return back()->withErrors($validator)->withInput();
        }

        $divisi = Divisi::create([
            'nama_divisi' => $request->nama_divisi,
            'nominal_lembur' => $request->nominal_lembur,
            'nominal_uang_makan' => $request->nominal_uang_makan,
            'nominal_ph' => $request->nominal_ph,
            'status' => 'A',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Divisi berhasil dibuat!',
                'data' => $divisi
            ]);
        }

        // Untuk Inertia, gunakan back() dengan success message
        return back()->with('success', 'Divisi berhasil dibuat!');
    }

    public function update(Request $request, $id)
    {
        \Log::info('Divisi update request received', [
            'id' => $id,
            'data' => $request->all(),
            'method' => $request->method(),
            'url' => $request->url(),
            'headers' => $request->headers->all()
        ]);

        $divisi = Divisi::findOrFail($id);

        $validator = Validator::make([
            'nama_divisi' => $request->nama_divisi,
            'nominal_lembur' => $request->nominal_lembur,
            'nominal_uang_makan' => $request->nominal_uang_makan,
            'nominal_ph' => $request->nominal_ph,
        ], [
            'nama_divisi' => 'required|string|max:255|unique:tbl_data_divisi,nama_divisi,' . $id . ',id',
            'nominal_lembur' => 'required|integer|min:0',
            'nominal_uang_makan' => 'required|integer|min:0',
            'nominal_ph' => 'nullable|integer|min:0',
        ], [
            'nama_divisi.required' => 'Nama divisi wajib diisi',
            'nama_divisi.unique' => 'Nama divisi sudah ada',
            'nominal_lembur.required' => 'Nominal lembur wajib diisi',
            'nominal_lembur.integer' => 'Nominal lembur harus berupa angka',
            'nominal_uang_makan.required' => 'Nominal uang makan wajib diisi',
            'nominal_uang_makan.integer' => 'Nominal uang makan harus berupa angka',
            'nominal_ph.integer' => 'Nominal PH harus berupa angka',
        ]);

        if ($validator->fails()) {
            \Log::error('Divisi update validation failed', [
                'errors' => $validator->errors()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return back()->withErrors($validator)->withInput();
        }

        $divisi->update([
            'nama_divisi' => $request->nama_divisi,
            'nominal_lembur' => $request->nominal_lembur,
            'nominal_uang_makan' => $request->nominal_uang_makan,
            'nominal_ph' => $request->nominal_ph,
        ]);

        \Log::info('Divisi updated successfully', [
            'id' => $id,
            'updated_data' => $divisi->fresh()->toArray()
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Divisi berhasil diperbarui!',
                'data' => $divisi->fresh()
            ]);
        }

        // Untuk Inertia, gunakan back() dengan success message
        return back()->with('success', 'Divisi berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $divisi = Divisi::findOrFail($id);
        $divisi->delete();

        return back()->with('success', 'Divisi berhasil dihapus!');
    }

    public function toggleStatus($id)
    {
        $divisi = Divisi::findOrFail($id);
        $divisi->update([
            'status' => $divisi->status === 'A' ? 'N' : 'A'
        ]);

        return back()->with('success', 'Status divisi berhasil diubah!');
    }
} 