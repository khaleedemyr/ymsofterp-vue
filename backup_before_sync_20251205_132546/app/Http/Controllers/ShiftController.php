<?php
namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::with('division')->orderBy('division_id')->orderBy('time_start')->get();
        return Inertia::render('Shift/Index', [
            'shifts' => $shifts,
        ]);
    }

    public function create()
    {
        $divisions = Division::orderBy('nama_divisi')->get();
        return Inertia::render('Shift/Create', [
            'divisions' => $divisions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'division_id' => 'required|exists:tbl_data_divisi,id',
            'shift_name' => 'required|string|max:100',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i',
        ]);
        Shift::create($validated);
        return redirect()->route('shifts.index')->with('success', 'Shift berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $shift = Shift::findOrFail($id);
        $divisions = Division::orderBy('nama_divisi')->get();
        return Inertia::render('Shift/Edit', [
            'shift' => $shift,
            'divisions' => $divisions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'division_id' => 'required|exists:tbl_data_divisi,id',
            'shift_name' => 'required|string|max:100',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i',
        ]);
        $shift = Shift::findOrFail($id);
        $shift->update($validated);
        return redirect()->route('shifts.index')->with('success', 'Shift berhasil diupdate!');
    }

    public function destroy($id)
    {
        $shift = Shift::findOrFail($id);
        $shift->delete();
        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dihapus!');
    }

    // API untuk dropdown
    public function apiDivisions()
    {
        $divisions = Division::orderBy('nama_divisi')->get();
        return response()->json(['divisions' => $divisions]);
    }
} 