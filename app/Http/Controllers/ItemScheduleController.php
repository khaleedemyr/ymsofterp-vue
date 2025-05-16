<?php
namespace App\Http\Controllers;

use App\Models\ItemSchedule;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ItemScheduleController extends Controller
{
    public function index()
    {
        $schedules = ItemSchedule::with('item')->orderBy('arrival_day')->orderBy('item_id')->get();
        return Inertia::render('ItemSchedule/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function create()
    {
        $items = Item::orderBy('name')->get();
        return Inertia::render('ItemSchedule/Form', [
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'arrival_day' => 'required|string',
            'notes' => 'nullable|string',
        ]);
        ItemSchedule::create($data);
        return redirect()->route('item-schedules.index')->with('success', 'Jadwal item berhasil ditambahkan');
    }

    public function edit($id)
    {
        $schedule = ItemSchedule::findOrFail($id);
        $items = Item::orderBy('name')->get();
        return Inertia::render('ItemSchedule/Form', [
            'editData' => $schedule,
            'items' => $items,
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'arrival_day' => 'required|string',
            'notes' => 'nullable|string',
        ]);
        $schedule = ItemSchedule::findOrFail($id);
        $schedule->update($data);
        return redirect()->route('item-schedules.index')->with('success', 'Jadwal item berhasil diupdate');
    }

    public function destroy($id)
    {
        $schedule = ItemSchedule::findOrFail($id);
        $schedule->delete();
        return redirect()->route('item-schedules.index')->with('success', 'Jadwal item berhasil dihapus');
    }

    public function getTodaySchedules()
    {
        $today = now()->format('l'); // e.g. 'Monday'
        $schedules = DB::table('item_schedules')
            ->join('items', 'item_schedules.item_id', '=', 'items.id')
            ->where('arrival_day', $today)
            ->select('item_schedules.*', 'items.name as item_name')
            ->get();
        return response()->json(['schedules' => $schedules]);
    }
} 