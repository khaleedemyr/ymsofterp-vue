<?php

namespace App\Http\Controllers;

use App\Models\MasterSoal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MasterSoalController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterSoal::with(['kategori', 'creator'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('tipe_soal') && $request->tipe_soal !== 'all') {
            $query->byTipe($request->tipe_soal);
        }

        // Filter kategori dihapus

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }
        }

        // Per page
        $perPage = $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50]) ? $perPage : 10;

        $soals = $query->paginate($perPage)->withQueryString();

        // Get filter options
        // Kategori dihapus sesuai permintaan

        $tipeSoalOptions = [
            ['value' => 'all', 'label' => 'Semua Tipe'],
            ['value' => 'essay', 'label' => 'Essay'],
            ['value' => 'pilihan_ganda', 'label' => 'Pilihan Ganda'],
            ['value' => 'yes_no', 'label' => 'Ya/Tidak']
        ];

        $statusOptions = [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Tidak Aktif']
        ];

        return Inertia::render('MasterSoal/IndexSimple', [
            'soals' => $soals,
            'tipeSoalOptions' => $tipeSoalOptions,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['search', 'tipe_soal', 'status', 'per_page'])
        ]);
    }

    public function create()
    {
        // Kategori dihapus sesuai permintaan
        $tipeSoalOptions = [
            ['value' => 'essay', 'label' => 'Essay'],
            ['value' => 'pilihan_ganda', 'label' => 'Pilihan Ganda'],
            ['value' => 'yes_no', 'label' => 'Ya/Tidak']
        ];

        return Inertia::render('MasterSoal/Create', [
            'tipeSoalOptions' => $tipeSoalOptions
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'tipe_soal' => 'required|in:essay,pilihan_ganda,yes_no',
            'pertanyaan' => 'required|string',
            'waktu_detik' => 'required|integer|min:1|max:3600',
            'jawaban_benar' => 'nullable|string|max:255',
            'pilihan_a' => 'nullable|string|max:500',
            'pilihan_b' => 'nullable|string|max:500',
            'pilihan_c' => 'nullable|string|max:500',
            'pilihan_d' => 'nullable|string|max:500',
            'skor' => 'required|numeric|min:0.01|max:100',
            'status' => 'required|in:active,inactive'
        ], [
            'judul.required' => 'Judul soal harus diisi',
            'tipe_soal.required' => 'Tipe soal harus dipilih',
            'tipe_soal.in' => 'Tipe soal tidak valid',
            'pertanyaan.required' => 'Pertanyaan harus diisi',
            'waktu_detik.required' => 'Waktu pengerjaan harus diisi',
            'waktu_detik.integer' => 'Waktu harus berupa angka',
            'waktu_detik.min' => 'Waktu minimal 1 detik',
            'waktu_detik.max' => 'Waktu maksimal 3600 detik (1 jam)',
            'skor.required' => 'Skor harus diisi',
            'skor.numeric' => 'Skor harus berupa angka',
            'skor.min' => 'Skor minimal 0.01',
            'skor.max' => 'Skor maksimal 100',
            'status.required' => 'Status harus dipilih',
            'status.in' => 'Status tidak valid'
        ]);

        // Validasi khusus berdasarkan tipe soal
        if ($request->tipe_soal === 'pilihan_ganda') {
            $request->validate([
                'jawaban_benar' => 'required|in:A,B,C,D',
                'pilihan_a' => 'required|string|max:500',
                'pilihan_b' => 'required|string|max:500',
                'pilihan_c' => 'required|string|max:500',
                'pilihan_d' => 'required|string|max:500'
            ], [
                'jawaban_benar.required' => 'Jawaban benar harus dipilih untuk pilihan ganda',
                'jawaban_benar.in' => 'Jawaban benar harus A, B, C, atau D',
                'pilihan_a.required' => 'Pilihan A harus diisi',
                'pilihan_b.required' => 'Pilihan B harus diisi',
                'pilihan_c.required' => 'Pilihan C harus diisi',
                'pilihan_d.required' => 'Pilihan D harus diisi'
            ]);
        }

        if ($request->tipe_soal === 'yes_no') {
            $request->validate([
                'jawaban_benar' => 'required|in:yes,no'
            ], [
                'jawaban_benar.required' => 'Jawaban benar harus dipilih untuk ya/tidak',
                'jawaban_benar.in' => 'Jawaban benar harus ya atau tidak'
            ]);
        }

        try {
            DB::beginTransaction();

            $soal = MasterSoal::create([
                'judul' => $request->judul,
                'tipe_soal' => $request->tipe_soal,
                'pertanyaan' => $request->pertanyaan,
                'waktu_detik' => $request->waktu_detik,
                'jawaban_benar' => $request->jawaban_benar,
                'pilihan_a' => $request->pilihan_a,
                'pilihan_b' => $request->pilihan_b,
                'pilihan_c' => $request->pilihan_c,
                'pilihan_d' => $request->pilihan_d,
                'skor' => $request->skor,
                'status' => $request->status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            return redirect()->route('master-soal.index')
                ->with('success', 'Soal berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show(MasterSoal $masterSoal)
    {
        $masterSoal->load(['kategori', 'creator', 'updater']);

        return Inertia::render('MasterSoal/Show', [
            'soal' => $masterSoal
        ]);
    }

    public function edit(MasterSoal $masterSoal)
    {
        // Kategori dihapus sesuai permintaan

        $tipeSoalOptions = [
            ['value' => 'essay', 'label' => 'Essay'],
            ['value' => 'pilihan_ganda', 'label' => 'Pilihan Ganda'],
            ['value' => 'yes_no', 'label' => 'Ya/Tidak']
        ];

        return Inertia::render('MasterSoal/Edit', [
            'soal' => $masterSoal,
            'tipeSoalOptions' => $tipeSoalOptions
        ]);
    }

    public function update(Request $request, MasterSoal $masterSoal)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'tipe_soal' => 'required|in:essay,pilihan_ganda,yes_no',
            'pertanyaan' => 'required|string',
            'waktu_detik' => 'required|integer|min:1|max:3600',
            'jawaban_benar' => 'nullable|string|max:255',
            'pilihan_a' => 'nullable|string|max:500',
            'pilihan_b' => 'nullable|string|max:500',
            'pilihan_c' => 'nullable|string|max:500',
            'pilihan_d' => 'nullable|string|max:500',
            'skor' => 'required|numeric|min:0.01|max:100',
            'status' => 'required|in:active,inactive'
        ], [
            'judul.required' => 'Judul soal harus diisi',
            'tipe_soal.required' => 'Tipe soal harus dipilih',
            'tipe_soal.in' => 'Tipe soal tidak valid',
            'pertanyaan.required' => 'Pertanyaan harus diisi',
            'waktu_detik.required' => 'Waktu pengerjaan harus diisi',
            'waktu_detik.integer' => 'Waktu harus berupa angka',
            'waktu_detik.min' => 'Waktu minimal 1 detik',
            'waktu_detik.max' => 'Waktu maksimal 3600 detik (1 jam)',
            'skor.required' => 'Skor harus diisi',
            'skor.numeric' => 'Skor harus berupa angka',
            'skor.min' => 'Skor minimal 0.01',
            'skor.max' => 'Skor maksimal 100',
            'status.required' => 'Status harus dipilih',
            'status.in' => 'Status tidak valid'
        ]);

        // Validasi khusus berdasarkan tipe soal
        if ($request->tipe_soal === 'pilihan_ganda') {
            $request->validate([
                'jawaban_benar' => 'required|in:A,B,C,D',
                'pilihan_a' => 'required|string|max:500',
                'pilihan_b' => 'required|string|max:500',
                'pilihan_c' => 'required|string|max:500',
                'pilihan_d' => 'required|string|max:500'
            ], [
                'jawaban_benar.required' => 'Jawaban benar harus dipilih untuk pilihan ganda',
                'jawaban_benar.in' => 'Jawaban benar harus A, B, C, atau D',
                'pilihan_a.required' => 'Pilihan A harus diisi',
                'pilihan_b.required' => 'Pilihan B harus diisi',
                'pilihan_c.required' => 'Pilihan C harus diisi',
                'pilihan_d.required' => 'Pilihan D harus diisi'
            ]);
        }

        if ($request->tipe_soal === 'yes_no') {
            $request->validate([
                'jawaban_benar' => 'required|in:yes,no'
            ], [
                'jawaban_benar.required' => 'Jawaban benar harus dipilih untuk ya/tidak',
                'jawaban_benar.in' => 'Jawaban benar harus ya atau tidak'
            ]);
        }

        try {
            DB::beginTransaction();

            $masterSoal->update([
                'judul' => $request->judul,
                'tipe_soal' => $request->tipe_soal,
                'pertanyaan' => $request->pertanyaan,
                'waktu_detik' => $request->waktu_detik,
                'jawaban_benar' => $request->jawaban_benar,
                'pilihan_a' => $request->pilihan_a,
                'pilihan_b' => $request->pilihan_b,
                'pilihan_c' => $request->pilihan_c,
                'pilihan_d' => $request->pilihan_d,
                'skor' => $request->skor,
                'status' => $request->status,
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            return redirect()->route('master-soal.index')
                ->with('success', 'Soal berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(MasterSoal $masterSoal)
    {
        try {
            $masterSoal->delete();

            return redirect()->route('master-soal.index')
                ->with('success', 'Soal berhasil dihapus!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus(MasterSoal $masterSoal)
    {
        try {
            $newStatus = $masterSoal->status === 'active' ? 'inactive' : 'active';
            
            $masterSoal->update([
                'status' => $newStatus,
                'updated_by' => Auth::id()
            ]);

            $statusText = $newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan';

            return redirect()->route('master-soal.index')
                ->with('success', "Soal berhasil {$statusText}!");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
