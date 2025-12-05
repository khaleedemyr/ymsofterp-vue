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
        $schedule = ItemSchedule::create($data);
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'create',
            'module' => 'item_schedule',
            'description' => 'Menambah jadwal item: ' . $schedule->item_id . ' - ' . $schedule->arrival_day,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $schedule->toArray(),
        ]);
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
        $oldData = $schedule->toArray();
        $schedule->update($data);
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'update',
            'module' => 'item_schedule',
            'description' => 'Update jadwal item: ' . $schedule->item_id . ' - ' . $schedule->arrival_day,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $schedule->fresh()->toArray(),
        ]);
        return redirect()->route('item-schedules.index')->with('success', 'Jadwal item berhasil diupdate');
    }

    public function destroy($id)
    {
        $schedule = ItemSchedule::findOrFail($id);
        $oldData = $schedule->toArray();
        $schedule->delete();
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'delete',
            'module' => 'item_schedule',
            'description' => 'Menghapus jadwal item: ' . $oldData['item_id'] . ' - ' . $oldData['arrival_day'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);
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