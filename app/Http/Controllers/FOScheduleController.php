<?php

namespace App\Http\Controllers;

use App\Models\FOSchedule;
use App\Models\Region;
use App\Models\Outlet;
use App\Models\WarehouseDivision;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FOScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = FOSchedule::with(['regions', 'outlets', 'warehouseDivisions'])
            ->orderBy('day')
            ->orderBy('open_time');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fo_mode', 'like', "%$search%")
                  ->orWhere('day', 'like', "%$search%")
                  ->orWhereHas('warehouseDivisions', function($wq) use ($search) {
                      $wq->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('regions', function($rq) use ($search) {
                      $rq->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('outlets', function($oq) use ($search) {
                      $oq->where('nama_outlet', 'like', "%$search%");
                  });
            });
        }

        $schedules = $query->paginate(10)->withQueryString();

        return Inertia::render('FOSchedule/Index', [
            'schedules' => $schedules,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('FOSchedule/Form', [
            'regions' => Region::where('status', 'active')->orderBy('name')->get(),
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(),
            'warehouseDivisions' => WarehouseDivision::orderBy('name')->get(),
            'foModes' => ['FO Utama', 'FO Tambahan', 'FO Pengambilan']
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fo_mode' => 'required|in:FO Utama,FO Tambahan,FO Pengambilan',
            'warehouse_division_ids' => 'required|array',
            'warehouse_division_ids.*' => 'exists:warehouse_division,id',
            'day' => 'required|string',
            'open_time' => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i',
            'region_ids' => 'required_without:outlet_ids|array',
            'region_ids.*' => 'exists:regions,id',
            'outlet_ids' => 'required_without:region_ids|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);
        
        if ($data['open_time'] === $data['close_time']) {
            return back()->withErrors(['close_time' => 'Jam tutup tidak boleh sama dengan jam buka.']);
        }

        $schedule = FOSchedule::create([
            'fo_mode' => $data['fo_mode'],
            'day' => $data['day'],
            'open_time' => $data['open_time'],
            'close_time' => $data['close_time']
        ]);

        $schedule->warehouseDivisions()->attach($data['warehouse_division_ids']);

        if (!empty($data['region_ids'])) {
            $schedule->regions()->attach($data['region_ids']);
        }

        if (!empty($data['outlet_ids'])) {
            $schedule->outlets()->attach($data['outlet_ids']);
        }

        return redirect()->route('fo-schedules.index')
            ->with('success', 'Jadwal FO berhasil ditambahkan');
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'fo_schedule',
            'description' => 'Membuat jadwal FO: ' . $schedule->fo_mode . ' - ' . $schedule->day,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $schedule->toArray(),
        ]);
    }

    public function edit($id)
    {
        $schedule = FOSchedule::with(['regions', 'outlets', 'warehouseDivisions'])->findOrFail($id);
        
        return Inertia::render('FOSchedule/Form', [
            'editData' => $schedule,
            'regions' => Region::where('status', 'active')->orderBy('name')->get(),
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(),
            'warehouseDivisions' => WarehouseDivision::orderBy('name')->get(),
            'foModes' => ['FO Utama', 'FO Tambahan', 'FO Pengambilan']
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'fo_mode' => 'required|in:FO Utama,FO Tambahan,FO Pengambilan',
            'warehouse_division_ids' => 'required|array',
            'warehouse_division_ids.*' => 'exists:warehouse_division,id',
            'day' => 'required|string',
            'open_time' => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i',
            'region_ids' => 'required_without:outlet_ids|array',
            'region_ids.*' => 'exists:regions,id',
            'outlet_ids' => 'required_without:region_ids|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);
        
        if ($data['open_time'] === $data['close_time']) {
            return back()->withErrors(['close_time' => 'Jam tutup tidak boleh sama dengan jam buka.']);
        }

        $schedule = FOSchedule::findOrFail($id);
        $schedule->update([
            'fo_mode' => $data['fo_mode'],
            'day' => $data['day'],
            'open_time' => $data['open_time'],
            'close_time' => $data['close_time']
        ]);

        $schedule->warehouseDivisions()->sync($data['warehouse_division_ids']);
        $schedule->regions()->sync($data['region_ids'] ?? []);
        $schedule->outlets()->sync($data['outlet_ids'] ?? []);

        return redirect()->route('fo-schedules.index')
            ->with('success', 'Jadwal FO berhasil diupdate');
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'fo_schedule',
            'description' => 'Update jadwal FO: ' . $schedule->fo_mode . ' - ' . $schedule->day,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $schedule->fresh()->toArray(),
        ]);
    }

    public function destroy($id)
    {
        $schedule = FOSchedule::findOrFail($id);
        $schedule->delete();
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'fo_schedule',
            'description' => 'Menghapus jadwal FO: ' . $schedule->fo_mode . ' - ' . $schedule->day,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $schedule->toArray(),
            'new_data' => null,
        ]);
        return redirect()->route('fo-schedules.index')
            ->with('success', 'Jadwal FO berhasil dihapus');
    }

    public function check(Request $request)
    {
        $request->validate([
            'fo_mode' => 'required',
            'day' => 'required',
        ]);

        date_default_timezone_set('Asia/Jakarta');
        $user = Auth::user();
        $id_outlet = $request->outlet_id;
        $region_id = $request->region_id;
        if (!$id_outlet && $user && $user->id_outlet) {
            $id_outlet = $user->id_outlet;
        }
        if (!$region_id && $id_outlet) {
            $region_id = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('region_id');
        }
        $regionExists = false;
        if ($region_id) {
            $regionExists = DB::table('regions')->where('id', $region_id)->where('status', 'active')->exists();
        }
        if (!$regionExists) {
            $region_id = null;
        }

        $now = now('Asia/Jakarta');
        $daysOfWeek = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $todayIdx = array_search($now->format('l'), $daysOfWeek);
        $todayName = $now->format('l');
        $currentTime = $now->format('H:i:s');

        // Ambil semua jadwal FO mode ini
        $schedules = FOSchedule::with(['warehouseDivisions', 'regions', 'outlets'])
            ->where('fo_mode', $request->fo_mode)
            ->get();

        // 1. Cek jadwal hari ini
        $todaySchedules = $schedules->where('day', $todayName);
        $activeWindow = null;
        foreach ($todaySchedules as $schedule) {
            $regionMatch = $region_id && $schedule->regions->pluck('id')->contains($region_id);
            $outletMatch = $id_outlet && $schedule->outlets->pluck('id_outlet')->contains($id_outlet);
            if (!($regionMatch || $outletMatch)) continue;
            $openTime = $schedule->open_time;
            $closeTime = $schedule->close_time;
            if (strtotime($openTime) < strtotime($closeTime)) {
                // Shift siang: window = hari ini jam open-close
                $openDT = $now->copy()->setTimeFromTimeString($openTime);
                $closeDT = $now->copy()->setTimeFromTimeString($closeTime);
                if ($now->gte($openDT) && $now->lt($closeDT)) {
                    $activeWindow = [
                        'schedule' => $schedule,
                        'open_datetime' => $openDT->toDateTimeString(),
                        'close_datetime' => $closeDT->toDateTimeString(),
                    ];
                    break;
                }
            } else {
                // Shift malam: window = 00:00 hari ini sampai jam close
                $openDT = $now->copy()->startOfDay();
                $closeDT = $now->copy()->setTimeFromTimeString($closeTime);
                if ($now->gte($openDT) && $now->lt($closeDT)) {
                    $activeWindow = [
                        'schedule' => $schedule,
                        'open_datetime' => $openDT->toDateTimeString(),
                        'close_datetime' => $closeDT->toDateTimeString(),
                    ];
                    break;
                }
            }
        }
        // 2. Jika sudah lewat jam close, cari jadwal berikutnya
        if (!$activeWindow) {
            // Cek jadwal berikutnya (max 7 hari ke depan)
            for ($i = 1; $i <= 7; $i++) {
                $nextIdx = ($todayIdx + $i) % 7;
                $nextDay = $daysOfWeek[$nextIdx];
                $nextSchedules = $schedules->where('day', $nextDay);
                foreach ($nextSchedules as $schedule) {
                    $regionMatch = $region_id && $schedule->regions->pluck('id')->contains($region_id);
                    $outletMatch = $id_outlet && $schedule->outlets->pluck('id_outlet')->contains($id_outlet);
                    if (!($regionMatch || $outletMatch)) continue;
                    $openTime = $schedule->open_time;
                    $closeTime = $schedule->close_time;
                    if (strtotime($openTime) > strtotime($closeTime)) {
                        // Shift malam: window = hari ini jam open sampai hari berikutnya jam close
                        $openDT = $now->copy()->setTimeFromTimeString($openTime);
                        $closeDT = $now->copy()->addDays($i)->setTimeFromTimeString($closeTime);
                        if ($now->gte($openDT) && $now->lt($closeDT)) {
                            $activeWindow = [
                                'schedule' => $schedule,
                                'open_datetime' => $openDT->toDateTimeString(),
                                'close_datetime' => $closeDT->toDateTimeString(),
                            ];
                            break 2;
                        }
                    }
                }
            }
        }
        if ($activeWindow) {
            $s = $activeWindow['schedule'];
            return response()->json([
                'schedule' => [
                    'id' => $s->id,
                    'fo_mode' => $s->fo_mode,
                    'day' => $s->day,
                    'open_time' => $s->open_time,
                    'close_time' => $s->close_time,
                    'warehouse_divisions' => $s->warehouseDivisions->map(function($wd) {
                        return [
                            'id' => $wd->id,
                            'name' => $wd->name
                        ];
                    }),
                    'regions' => $s->regions->map(function($r) {
                        return [
                            'id' => $r->id,
                            'name' => $r->name
                        ];
                    }),
                    'outlets' => $s->outlets->map(function($o) {
                        return [
                            'id_outlet' => $o->id_outlet,
                            'nama_outlet' => $o->nama_outlet
                        ];
                    }),
                    'open_datetime' => $activeWindow['open_datetime'],
                    'close_datetime' => $activeWindow['close_datetime'],
                    'is_active' => true,
                ]
            ]);
        } else {
            return response()->json(['schedule' => null, 'error' => 'Di luar jam operasional']);
        }
    }

    /**
     * Get all FO schedules for a given outlet (and region if needed)
     */
    public function getOutletSchedules(Request $request)
    {
        $user = Auth::user();
        $id_outlet = $request->outlet_id;
        $region_id = $request->region_id;

        // Jika outlet_id tidak dikirim, ambil dari user
        if (!$id_outlet && $user && $user->id_outlet) {
            $id_outlet = $user->id_outlet;
        }
        // Jika region_id tidak dikirim, ambil dari tbl_data_outlet
        if (!$region_id && $id_outlet) {
            $region_id = \DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('region_id');
        }
        // Pastikan region aktif
        $regionExists = false;
        if ($region_id) {
            $regionExists = \DB::table('regions')->where('id', $region_id)->where('status', 'active')->exists();
        }
        if (!$regionExists) {
            $region_id = null;
        }
        // Ambil semua jadwal FO yang terkait outlet atau region
        $query = FOSchedule::with(['warehouseDivisions', 'regions', 'outlets'])
            ->orderBy('day')
            ->orderBy('open_time');
        $query->where(function($q) use ($region_id, $id_outlet) {
            if ($region_id && $id_outlet) {
                $q->whereHas('regions', function($rq) use ($region_id) {
                    $rq->where('regions.id', $region_id);
                })
                ->orWhereHas('outlets', function($oq) use ($id_outlet) {
                    $oq->where('tbl_data_outlet.id_outlet', $id_outlet);
                });
            } elseif ($region_id) {
                $q->whereHas('regions', function($rq) use ($region_id) {
                    $rq->where('regions.id', $region_id);
                });
            } elseif ($id_outlet) {
                $q->whereHas('outlets', function($oq) use ($id_outlet) {
                    $oq->where('tbl_data_outlet.id_outlet', $id_outlet);
                });
            }
        });
        $schedules = $query->get();
        return response()->json([
            'schedules' => $schedules->map(function($s) {
                return [
                    'id' => $s->id,
                    'fo_mode' => $s->fo_mode,
                    'day' => $s->day,
                    'open_time' => $s->open_time,
                    'close_time' => $s->close_time,
                    'warehouse_divisions' => $s->warehouseDivisions->map(function($wd) {
                        return [
                            'id' => $wd->id,
                            'name' => $wd->name
                        ];
                    }),
                ];
            })
        ]);
    }

    /**
     * Render Form Floor Order dengan props user lengkap (relasi outlet)
     */
    public function createFloorOrder(Request $request)
    {
        $user = auth()->user()->load('outlet');
        return \Inertia\Inertia::render('Pages/FloorOrder/Form', [
            'user' => $user,
            // Tambahkan props lain jika perlu
        ]);
    }
} 