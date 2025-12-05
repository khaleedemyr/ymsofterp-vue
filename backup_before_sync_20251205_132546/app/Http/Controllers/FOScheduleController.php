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
use Illuminate\Support\Facades\Log;

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
            'foModes' => ['RO Utama', 'RO Tambahan', 'RO Pengambilan']
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fo_mode' => 'required|in:RO Utama,RO Tambahan,RO Pengambilan',
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
            'description' => 'Membuat jadwal RO: ' . $schedule->fo_mode . ' - ' . $schedule->day,
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
            'foModes' => ['RO Utama', 'RO Tambahan', 'RO Pengambilan']
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'fo_mode' => 'required|in:RO Utama,RO Tambahan,RO Pengambilan',
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
        \Log::info('DEBUG FO CHECK region_id', [
            'region_id_param' => $request->region_id,
            'outlet_id_param' => $request->outlet_id,
            'all_params' => $request->all()
        ]);
        $user = Auth::user();
        $id_outlet = $request->outlet_id;
        $region_id = $request->region_id;
        if (!$region_id && $id_outlet) {
            $region_id = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('region_id');
        }
        \Log::error('[FO DEBUG] PARAMS', [
            'fo_mode' => $request->fo_mode,
            'day' => $request->day,
            'region_id' => $region_id,
            'outlet_id' => $id_outlet,
            'all' => $request->all(),
        ]);
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

        $activeWindow = null;
        foreach ($schedules as $schedule) {
            $regionMatch = $region_id && $schedule->regions->pluck('id')->contains($region_id);
            $outletMatch = $id_outlet && $schedule->outlets->pluck('id_outlet')->contains($id_outlet);
            \Log::error('[FO DEBUG] Jadwal Filter', [
                'schedule_id' => $schedule->id,
                'regionMatch' => $regionMatch,
                'outletMatch' => $outletMatch,
                'schedule_regions' => $schedule->regions->pluck('id')->toArray(),
                'schedule_outlets' => $schedule->outlets->pluck('id_outlet')->toArray(),
                'region_id' => $region_id,
                'id_outlet' => $id_outlet,
            ]);
            if (!($regionMatch || $outletMatch)) continue;

            $openTime = $schedule->open_time;
            $closeTime = $schedule->close_time;
            $scheduleDayIdx = array_search($schedule->day, $daysOfWeek);

            if (strtotime($openTime) < strtotime($closeTime)) {
                if ($todayName == $schedule->day) {
                    $openDT = $now->copy()->setTimeFromTimeString($openTime);
                    $closeDT = $now->copy()->setTimeFromTimeString($closeTime);
                    Log::info('[FO DEBUG] Shift siang', [
                        'openDT' => $openDT->toDateTimeString(),
                        'closeDT' => $closeDT->toDateTimeString(),
                        'now' => $now->toDateTimeString(),
                    ]);
                    if ($now->gte($openDT) && $now->lt($closeDT)) {
                        Log::info('[FO DEBUG] Jadwal AKTIF (siang)');
                        $activeWindow = [
                            'schedule' => $schedule,
                            'open_datetime' => $openDT->toDateTimeString(),
                            'close_datetime' => $closeDT->toDateTimeString(),
                        ];
                        break;
                    }
                }
            } else {
                $prevDayIdx = ($scheduleDayIdx - 1) < 0 ? 6 : ($scheduleDayIdx - 1);
                $prevDay = $daysOfWeek[$prevDayIdx];
                if ($todayName == $schedule->day) {
                    $openDT = $now->copy()->subDay()->setTimeFromTimeString($openTime);
                    $closeDT = $now->copy()->setTimeFromTimeString($closeTime);
                    Log::info('[FO DEBUG] Shift malam (window close)', [
                        'openDT' => $openDT->toDateTimeString(),
                        'closeDT' => $closeDT->toDateTimeString(),
                        'now' => $now->toDateTimeString(),
                    ]);
                    if ($now->gte($openDT) && $now->lt($closeDT)) {
                        Log::info('[FO DEBUG] Jadwal AKTIF (malam, window close)');
                        $activeWindow = [
                            'schedule' => $schedule,
                            'open_datetime' => $openDT->toDateTimeString(),
                            'close_datetime' => $closeDT->toDateTimeString(),
                        ];
                        break;
                    }
                } elseif ($todayName == $prevDay) {
                    $openDT = $now->copy()->setTimeFromTimeString($openTime);
                    $closeDT = $now->copy()->addDay()->setTimeFromTimeString($closeTime);
                    Log::info('[FO DEBUG] Shift malam (window open)', [
                        'openDT' => $openDT->toDateTimeString(),
                        'closeDT' => $closeDT->toDateTimeString(),
                        'now' => $now->toDateTimeString(),
                    ]);
                    if ($now->gte($openDT)) {
                        Log::info('[FO DEBUG] Jadwal AKTIF (malam, window open)');
                        $activeWindow = [
                            'schedule' => $schedule,
                            'open_datetime' => $openDT->toDateTimeString(),
                            'close_datetime' => $closeDT->toDateTimeString(),
                        ];
                        break;
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