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
            'nominal_ph' => 'required|integer|min:0',
        ], [
            'nama_divisi.required' => 'Nama divisi wajib diisi',
            'nama_divisi.unique' => 'Nama divisi sudah ada',
            'nominal_lembur.required' => 'Nominal lembur wajib diisi',
            'nominal_lembur.integer' => 'Nominal lembur harus berupa angka',
            'nominal_uang_makan.required' => 'Nominal uang makan wajib diisi',
            'nominal_uang_makan.integer' => 'Nominal uang makan harus berupa angka',
            'nominal_ph.required' => 'Nominal PH wajib diisi',
            'nominal_ph.integer' => 'Nominal PH harus berupa angka',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Divisi::create([
            'nama_divisi' => $request->nama_divisi,
            'nominal_lembur' => $request->nominal_lembur,
            'nominal_uang_makan' => $request->nominal_uang_makan,
            'nominal_ph' => $request->nominal_ph,
            'status' => 'A',
        ]);

        return redirect()->route('divisis.index')->with('success', 'Divisi berhasil dibuat!');
    }

    public function update(Request $request, $id)
    {
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
            'nominal_ph' => 'required|integer|min:0',
        ], [
            'nama_divisi.required' => 'Nama divisi wajib diisi',
            'nama_divisi.unique' => 'Nama divisi sudah ada',
            'nominal_lembur.required' => 'Nominal lembur wajib diisi',
            'nominal_lembur.integer' => 'Nominal lembur harus berupa angka',
            'nominal_uang_makan.required' => 'Nominal uang makan wajib diisi',
            'nominal_uang_makan.integer' => 'Nominal uang makan harus berupa angka',
            'nominal_ph.required' => 'Nominal PH wajib diisi',
            'nominal_ph.integer' => 'Nominal PH harus berupa angka',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $divisi->update([
            'nama_divisi' => $request->nama_divisi,
            'nominal_lembur' => $request->nominal_lembur,
            'nominal_uang_makan' => $request->nominal_uang_makan,
            'nominal_ph' => $request->nominal_ph,
        ]);

        return redirect()->route('divisis.index')->with('success', 'Divisi berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $divisi = Divisi::findOrFail($id);
        $divisi->delete();

        return redirect()->route('divisis.index')->with('success', 'Divisi berhasil dihapus!');
    }

    public function toggleStatus($id)
    {
        $divisi = Divisi::findOrFail($id);
        $divisi->update([
            'status' => $divisi->status === 'A' ? 'N' : 'A'
        ]);

        return redirect()->route('divisis.index')->with('success', 'Status divisi berhasil diubah!');
    }
} 