<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return redirect()->back()->with('success', 'Libur nasional berhasil diupdate!');
    }

    public function destroy($id)
    {
        DB::table('tbl_kalender_perusahaan')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Libur nasional berhasil dihapus!');
    }
} 