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
    }

    public function destroy($id)
    {
        $schedule = FOSchedule::findOrFail($id);
        $schedule->delete();
        return redirect()->route('fo-schedules.index')
            ->with('success', 'Jadwal FO berhasil dihapus');
    }

    public function check(Request $request)
    {
        $request->validate([
            'fo_mode' => 'required',
            'day' => 'required',
        ]);

        // Ambil user login
        $user = Auth::user();
        $id_outlet = $request->outlet_id;
        $region_id = $request->region_id;

        // Jika outlet_id tidak dikirim, ambil dari user
        if (!$id_outlet && $user && $user->id_outlet) {
            $id_outlet = $user->id_outlet;
        }

        // Jika region_id tidak dikirim, ambil dari tbl_data_outlet
        if (!$region_id && $id_outlet) {
            $region_id = DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('region_id');
        }

        // Pastikan region aktif
        $regionExists = false;
        if ($region_id) {
            $regionExists = DB::table('regions')->where('id', $region_id)->where('status', 'active')->exists();
        }
        if (!$regionExists) {
            $region_id = null;
        }

        $now = now();
        $currentDay = $now->format('l');
        
        // Cek jadwal hari ini (untuk jam close)
        $todaySchedule = FOSchedule::with(['warehouseDivisions', 'regions', 'outlets'])
            ->where('fo_mode', $request->fo_mode)
            ->where('day', $currentDay)
            ->first();

        $found = false;
        $isActive = false;
        $openDateTime = null;
        $closeDateTime = null;
        $schedule = null;

        // Cek jadwal hari ini untuk jam close
        if ($todaySchedule) {
            $regionMatch = $region_id && $todaySchedule->regions->pluck('id')->contains($region_id);
            $outletMatch = $id_outlet && $todaySchedule->outlets->pluck('id_outlet')->contains($id_outlet);

            if ($regionMatch || $outletMatch) {
                $found = true;
                $schedule = $todaySchedule;
                
                // Set jam close hari ini
                $closeDateTime = $now->copy()->setTimeFromTimeString($todaySchedule->close_time);
                
                // Jika sekarang sudah lewat jam close, cari jadwal berikutnya
                if ($now->greaterThanOrEqualTo($closeDateTime)) {
                    // Cari jadwal berikutnya (bisa hari besok atau hari lainnya)
                    $nextSchedule = null;
                    $checkDate = $now->copy()->addDay();
                    
                    // Cek maksimal 7 hari ke depan
                    for ($i = 0; $i < 7; $i++) {
                        $nextDay = $checkDate->format('l');
                        $nextSchedule = FOSchedule::with(['warehouseDivisions', 'regions', 'outlets'])
                            ->where('fo_mode', $request->fo_mode)
                            ->where('day', $nextDay)
                            ->first();
                            
                        if ($nextSchedule) {
                            $regionMatch = $region_id && $nextSchedule->regions->pluck('id')->contains($region_id);
                            $outletMatch = $id_outlet && $nextSchedule->outlets->pluck('id_outlet')->contains($id_outlet);
                            
                            if ($regionMatch || $outletMatch) {
                                break;
                            }
                        }
                        
                        $checkDate->addDay();
                    }
                    
                    if ($nextSchedule) {
                        $schedule = $nextSchedule;
                        // Jika shift malam (open > close), openDateTime = hari sebelum hari jadwal jam open, closeDateTime = hari jadwal jam close
                        if (strtotime($nextSchedule->open_time) > strtotime($nextSchedule->close_time)) {
                            $nextScheduleDate = $now->copy()->next($nextSchedule->day);
                            $openDateTime = $nextScheduleDate->copy()->subDay()->setTimeFromTimeString($nextSchedule->open_time);
                            $closeDateTime = $nextScheduleDate->copy()->setTimeFromTimeString($nextSchedule->close_time);
                        } else {
                            // Jika shift siang, openDateTime = hari jadwal jam open, closeDateTime = hari jadwal jam close
                            $nextScheduleDate = $now->copy()->next($nextSchedule->day);
                            $openDateTime = $nextScheduleDate->copy()->setTimeFromTimeString($nextSchedule->open_time);
                            $closeDateTime = $nextScheduleDate->copy()->setTimeFromTimeString($nextSchedule->close_time);
                        }
                    }
                } else {
                    // Jika belum lewat jam close, window aktif dari 00:00 sampai jam close
                    $openDateTime = $now->copy()->startOfDay();
                }
            }
        }

        // Cek apakah sekarang dalam window aktif
        if ($found && $openDateTime && $closeDateTime) {
            if ($now->between($openDateTime, $closeDateTime)) {
                $isActive = true;
            }
        }

     

        if ($found) {
            return response()->json([
                'schedule' => [
                    'id' => $schedule->id,
                    'fo_mode' => $schedule->fo_mode,
                    'day' => $schedule->day,
                    'open_time' => $schedule->open_time,
                    'close_time' => $schedule->close_time,
                    'warehouse_divisions' => $schedule->warehouseDivisions->map(function($wd) {
                        return [
                            'id' => $wd->id,
                            'name' => $wd->name
                        ];
                    }),
                    'regions' => $schedule->regions->map(function($r) {
                        return [
                            'id' => $r->id,
                            'name' => $r->name
                        ];
                    }),
                    'outlets' => $schedule->outlets->map(function($o) {
                        return [
                            'id_outlet' => $o->id_outlet,
                            'nama_outlet' => $o->nama_outlet
                        ];
                    }),
                    'open_datetime' => $openDateTime ? $openDateTime->toDateTimeString() : null,
                    'close_datetime' => $closeDateTime ? $closeDateTime->toDateTimeString() : null,
                    'is_active' => $isActive,
                ]
            ]);
        } else {
            return response()->json(['schedule' => null]);
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