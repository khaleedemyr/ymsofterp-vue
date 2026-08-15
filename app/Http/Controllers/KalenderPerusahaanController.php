<?php
namespace App\Http\Controllers;

use App\Services\HolidayAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class KalenderPerusahaanController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $search = $request->input('search', '');
        $query = DB::table('tbl_kalender_perusahaan')
            ->whereYear('tgl_libur', $year);
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('keterangan', 'like', "%$search%")
                  ->orWhere('tgl_libur', 'like', "%$search%") ;
            });
        }
        $libur = $query->orderBy('tgl_libur')->paginate(10)->withQueryString();
        $years = DB::table('tbl_kalender_perusahaan')
            ->selectRaw('YEAR(tgl_libur) as year')
            ->groupBy('year')->orderBy('year','desc')->pluck('year');
        return Inertia::render('KalenderPerusahaan/Index', [
            'libur' => $libur,
            'years' => $years,
            'filter' => [ 'year' => $year, 'search' => $search ]
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tgl_libur' => 'required|date|unique:tbl_kalender_perusahaan,tgl_libur',
            'keterangan' => 'required|string|max:255',
        ]);
        DB::table('tbl_kalender_perusahaan')->insert([
            'tgl_libur' => $data['tgl_libur'],
            'keterangan' => $data['keterangan'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->autoProcessHolidayAttendanceIfDue($data['tgl_libur']);
        return redirect()->back()->with('success', 'Libur nasional berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'tgl_libur' => 'required|date|unique:tbl_kalender_perusahaan,tgl_libur,' . $id,
            'keterangan' => 'required|string|max:255',
        ]);
        DB::table('tbl_kalender_perusahaan')->where('id', $id)->update([
            'tgl_libur' => $data['tgl_libur'],
            'keterangan' => $data['keterangan'],
            'updated_at' => now(),
        ]);
        $this->autoProcessHolidayAttendanceIfDue($data['tgl_libur']);
        return redirect()->back()->with('success', 'Libur nasional berhasil diupdate!');
    }

    public function destroy($id)
    {
        DB::table('tbl_kalender_perusahaan')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Libur nasional berhasil dihapus!');
    }

    private function autoProcessHolidayAttendanceIfDue(string $holidayDate): void
    {
        if (Carbon::parse($holidayDate)->gt(Carbon::today())) {
            return;
        }

        try {
            $results = app(HolidayAttendanceService::class)->processHolidayAttendance($holidayDate);
            Log::info('Holiday attendance auto-processed after calendar save', [
                'holiday_date' => $holidayDate,
                'processed' => $results['processed'] ?? 0,
                'bonus_paid' => $results['bonus_paid'] ?? 0,
                'errors' => $results['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('Holiday attendance auto-process after calendar save failed', [
                'holiday_date' => $holidayDate,
                'error' => $e->getMessage(),
            ]);
        }
    }
} 