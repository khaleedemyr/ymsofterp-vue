<?php

namespace App\Http\Controllers;

use App\Models\MasterSoal;
use App\Models\SoalPertanyaan;
use App\Models\KategoriSoal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MasterSoalNewController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterSoal::with(['creator', 'pertanyaans'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('judul', 'like', "%{$request->search}%")
                  ->orWhere('deskripsi', 'like', "%{$request->search}%");
            });
        }


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
        $statusOptions = [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Tidak Aktif']
        ];

        return Inertia::render('MasterSoalNew/Index', [
            'soals' => $soals,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['search', 'status', 'per_page'])
        ]);
    }

    public function create()
    {
        $tipeSoalOptions = [
            ['value' => 'essay', 'label' => 'Essay'],
            ['value' => 'pilihan_ganda', 'label' => 'Pilihan Ganda'],
            ['value' => 'yes_no', 'label' => 'Ya/Tidak']
        ];

        return Inertia::render('MasterSoalNew/Create', [
            'tipeSoalOptions' => $tipeSoalOptions
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'pertanyaans' => 'required|array|min:1',
            'pertanyaans.*.tipe_soal' => 'required|in:essay,pilihan_ganda,yes_no',
            'pertanyaans.*.pertanyaan' => 'required|string',
            'pertanyaans.*.waktu_detik' => 'required|integer|min:1|max:1800',
            'pertanyaans.*.skor' => 'nullable|numeric|min:0.01|max:100',
            'pertanyaans.*.jawaban_benar' => 'nullable|string|max:255',
            'pertanyaans.*.pilihan_a' => 'nullable|string|max:500',
            'pertanyaans.*.pilihan_b' => 'nullable|string|max:500',
            'pertanyaans.*.pilihan_c' => 'nullable|string|max:500',
            'pertanyaans.*.pilihan_d' => 'nullable|string|max:500',
            // Image validation
            'pertanyaans.*.pertanyaan_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_a_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_b_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_c_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_d_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'judul.required' => 'Judul soal harus diisi',
            'pertanyaans.required' => 'Minimal harus ada 1 pertanyaan',
            'pertanyaans.min' => 'Minimal harus ada 1 pertanyaan',
            'status.required' => 'Status harus dipilih',
            'status.in' => 'Status tidak valid'
        ]);

        // Validasi khusus untuk setiap pertanyaan
        foreach ($request->pertanyaans as $index => $pertanyaan) {
            if ($pertanyaan['tipe_soal'] === 'pilihan_ganda') {
                if (empty($pertanyaan['jawaban_benar']) || !in_array($pertanyaan['jawaban_benar'], ['A', 'B', 'C', 'D'])) {
                    return redirect()->back()
                        ->withErrors(["pertanyaans.{$index}.jawaban_benar" => 'Jawaban benar harus dipilih untuk pilihan ganda'])
                        ->withInput();
                }
                if (empty($pertanyaan['pilihan_a']) || empty($pertanyaan['pilihan_b']) || 
                    empty($pertanyaan['pilihan_c']) || empty($pertanyaan['pilihan_d'])) {
                    return redirect()->back()
                        ->withErrors(["pertanyaans.{$index}.pilihan_a" => 'Semua pilihan harus diisi untuk pilihan ganda'])
                        ->withInput();
                }
            }

            if ($pertanyaan['tipe_soal'] === 'yes_no') {
                if (empty($pertanyaan['jawaban_benar']) || !in_array($pertanyaan['jawaban_benar'], ['yes', 'no'])) {
                    return redirect()->back()
                        ->withErrors(["pertanyaans.{$index}.jawaban_benar" => 'Jawaban benar harus dipilih untuk ya/tidak'])
                        ->withInput();
                }
            }
        }

        try {
            DB::beginTransaction();

            // Hitung total skor
            $totalSkor = array_sum(array_column($request->pertanyaans, 'skor'));

            // Create master soal
            $masterSoal = MasterSoal::create([
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'skor_total' => $totalSkor,
                'status' => $request->status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            // Create pertanyaans
            foreach ($request->pertanyaans as $index => $pertanyaanData) {
                // Handle pertanyaan images
                $pertanyaanImages = [];
                if ($request->hasFile("pertanyaans.{$index}.pertanyaan_images")) {
                    foreach ($request->file("pertanyaans.{$index}.pertanyaan_images") as $image) {
                        $path = $image->store('master-soal/pertanyaan', 'public');
                        $pertanyaanImages[] = $path;
                    }
                }

                // Handle pilihan images
                $pilihanAImage = null;
                $pilihanBImage = null;
                $pilihanCImage = null;
                $pilihanDImage = null;

                if ($request->hasFile("pertanyaans.{$index}.pilihan_a_image")) {
                    $pilihanAImage = $request->file("pertanyaans.{$index}.pilihan_a_image")->store('master-soal/pilihan', 'public');
                }
                if ($request->hasFile("pertanyaans.{$index}.pilihan_b_image")) {
                    $pilihanBImage = $request->file("pertanyaans.{$index}.pilihan_b_image")->store('master-soal/pilihan', 'public');
                }
                if ($request->hasFile("pertanyaans.{$index}.pilihan_c_image")) {
                    $pilihanCImage = $request->file("pertanyaans.{$index}.pilihan_c_image")->store('master-soal/pilihan', 'public');
                }
                if ($request->hasFile("pertanyaans.{$index}.pilihan_d_image")) {
                    $pilihanDImage = $request->file("pertanyaans.{$index}.pilihan_d_image")->store('master-soal/pilihan', 'public');
                }

                SoalPertanyaan::create([
                    'master_soal_id' => $masterSoal->id,
                    'urutan' => $index + 1,
                    'tipe_soal' => $pertanyaanData['tipe_soal'],
                    'pertanyaan' => $pertanyaanData['pertanyaan'],
                    'pertanyaan_gambar' => !empty($pertanyaanImages) ? json_encode($pertanyaanImages) : null,
                    'waktu_detik' => $pertanyaanData['waktu_detik'],
                    'jawaban_benar' => $pertanyaanData['jawaban_benar'],
                    'pilihan_a' => $pertanyaanData['pilihan_a'] ?? null,
                    'pilihan_a_gambar' => $pilihanAImage,
                    'pilihan_b' => $pertanyaanData['pilihan_b'] ?? null,
                    'pilihan_b_gambar' => $pilihanBImage,
                    'pilihan_c' => $pertanyaanData['pilihan_c'] ?? null,
                    'pilihan_c_gambar' => $pilihanCImage,
                    'pilihan_d' => $pertanyaanData['pilihan_d'] ?? null,
                    'pilihan_d_gambar' => $pilihanDImage,
                    'skor' => $pertanyaanData['skor'],
                    'status' => 'active'
                ]);
            }

            DB::commit();

            return redirect()->route('master-soal-new.index')
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
        $masterSoal->load(['creator', 'updater', 'pertanyaans' => function($query) {
            $query->orderBy('urutan');
        }]);

        return Inertia::render('MasterSoalNew/Show', [
            'masterSoal' => $masterSoal
        ]);
    }

    public function edit(MasterSoal $masterSoal)
    {
        $masterSoal->load(['pertanyaans' => function($query) {
            $query->orderBy('urutan');
        }]);

        $tipeSoalOptions = [
            ['value' => 'essay', 'label' => 'Essay'],
            ['value' => 'pilihan_ganda', 'label' => 'Pilihan Ganda'],
            ['value' => 'yes_no', 'label' => 'Ya/Tidak']
        ];

        return Inertia::render('MasterSoalNew/Edit', [
            'masterSoal' => $masterSoal,
            'tipeSoalOptions' => $tipeSoalOptions
        ]);
    }

    public function update(Request $request, MasterSoal $masterSoal)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'pertanyaans' => 'required|array|min:1',
            'pertanyaans.*.tipe_soal' => 'required|in:essay,pilihan_ganda,yes_no',
            'pertanyaans.*.pertanyaan' => 'required|string',
            'pertanyaans.*.waktu_detik' => 'required|integer|min:1|max:1800',
            'pertanyaans.*.skor' => 'nullable|numeric|min:0.01|max:100',
            'pertanyaans.*.jawaban_benar' => 'nullable|string|max:255',
            'pertanyaans.*.pilihan_a' => 'nullable|string|max:500',
            'pertanyaans.*.pilihan_b' => 'nullable|string|max:500',
            'pertanyaans.*.pilihan_c' => 'nullable|string|max:500',
            'pertanyaans.*.pilihan_d' => 'nullable|string|max:500',
            // Image validation
            'pertanyaans.*.pertanyaan_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_a_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_b_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_c_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pertanyaans.*.pilihan_d_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Validasi khusus untuk setiap pertanyaan
        foreach ($request->pertanyaans as $index => $pertanyaan) {
            if ($pertanyaan['tipe_soal'] === 'pilihan_ganda') {
                if (empty($pertanyaan['jawaban_benar']) || !in_array($pertanyaan['jawaban_benar'], ['A', 'B', 'C', 'D'])) {
                    return redirect()->back()
                        ->withErrors(["pertanyaans.{$index}.jawaban_benar" => 'Jawaban benar harus dipilih untuk pilihan ganda'])
                        ->withInput();
                }
            }

            if ($pertanyaan['tipe_soal'] === 'yes_no') {
                if (empty($pertanyaan['jawaban_benar']) || !in_array($pertanyaan['jawaban_benar'], ['yes', 'no'])) {
                    return redirect()->back()
                        ->withErrors(["pertanyaans.{$index}.jawaban_benar" => 'Jawaban benar harus dipilih untuk ya/tidak'])
                        ->withInput();
                }
            }
        }

        try {
            DB::beginTransaction();

            // Hitung total skor
            $totalSkor = array_sum(array_column($request->pertanyaans, 'skor'));

            // Update master soal
            $masterSoal->update([
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'skor_total' => $totalSkor,
                'status' => $request->status,
                'updated_by' => Auth::id()
            ]);

            // Delete existing pertanyaans
            $masterSoal->pertanyaans()->delete();

            // Create new pertanyaans
            foreach ($request->pertanyaans as $index => $pertanyaanData) {
                // Handle pertanyaan images
                $pertanyaanImages = [];
                if ($request->hasFile("pertanyaans.{$index}.pertanyaan_images")) {
                    foreach ($request->file("pertanyaans.{$index}.pertanyaan_images") as $image) {
                        $path = $image->store('master-soal/pertanyaan', 'public');
                        $pertanyaanImages[] = $path;
                    }
                }

                // Handle pilihan images
                $pilihanAImage = null;
                $pilihanBImage = null;
                $pilihanCImage = null;
                $pilihanDImage = null;

                if ($request->hasFile("pertanyaans.{$index}.pilihan_a_image")) {
                    $pilihanAImage = $request->file("pertanyaans.{$index}.pilihan_a_image")->store('master-soal/pilihan', 'public');
                }
                if ($request->hasFile("pertanyaans.{$index}.pilihan_b_image")) {
                    $pilihanBImage = $request->file("pertanyaans.{$index}.pilihan_b_image")->store('master-soal/pilihan', 'public');
                }
                if ($request->hasFile("pertanyaans.{$index}.pilihan_c_image")) {
                    $pilihanCImage = $request->file("pertanyaans.{$index}.pilihan_c_image")->store('master-soal/pilihan', 'public');
                }
                if ($request->hasFile("pertanyaans.{$index}.pilihan_d_image")) {
                    $pilihanDImage = $request->file("pertanyaans.{$index}.pilihan_d_image")->store('master-soal/pilihan', 'public');
                }

                SoalPertanyaan::create([
                    'master_soal_id' => $masterSoal->id,
                    'urutan' => $index + 1,
                    'tipe_soal' => $pertanyaanData['tipe_soal'],
                    'pertanyaan' => $pertanyaanData['pertanyaan'],
                    'pertanyaan_gambar' => !empty($pertanyaanImages) ? json_encode($pertanyaanImages) : null,
                    'waktu_detik' => $pertanyaanData['waktu_detik'],
                    'jawaban_benar' => $pertanyaanData['jawaban_benar'],
                    'pilihan_a' => $pertanyaanData['pilihan_a'] ?? null,
                    'pilihan_a_gambar' => $pilihanAImage,
                    'pilihan_b' => $pertanyaanData['pilihan_b'] ?? null,
                    'pilihan_b_gambar' => $pilihanBImage,
                    'pilihan_c' => $pertanyaanData['pilihan_c'] ?? null,
                    'pilihan_c_gambar' => $pilihanCImage,
                    'pilihan_d' => $pertanyaanData['pilihan_d'] ?? null,
                    'pilihan_d_gambar' => $pilihanDImage,
                    'skor' => $pertanyaanData['skor'],
                    'status' => 'active'
                ]);
            }

            DB::commit();

            return redirect()->route('master-soal-new.index')
                ->with('success', 'Soal berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function duplicate(MasterSoal $masterSoal)
    {
        try {
            DB::beginTransaction();

            // Load pertanyaans with all data - use fresh query
            $pertanyaans = SoalPertanyaan::where('master_soal_id', $masterSoal->id)->get();

            // Create new master soal with duplicated data
            $newMasterSoal = MasterSoal::create([
                'judul' => $masterSoal->judul . ' (Copy)',
                'deskripsi' => $masterSoal->deskripsi,
                'status' => 'active', // Set as active by default
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            // Debug logging
            \Log::info('Duplicating master soal', [
                'original_id' => $masterSoal->id,
                'new_id' => $newMasterSoal->id,
                'pertanyaans_count' => $pertanyaans->count()
            ]);

            // Debug: Check original data
            foreach ($pertanyaans as $originalPertanyaan) {
                \Log::info('Original pertanyaan data', [
                    'id' => $originalPertanyaan->id,
                    'pertanyaan_gambar_raw' => $originalPertanyaan->pertanyaan_gambar,
                    'pertanyaan_gambar_type' => gettype($originalPertanyaan->pertanyaan_gambar),
                    'pilihan_a_gambar_raw' => $originalPertanyaan->pilihan_a_gambar,
                    'pilihan_b_gambar_raw' => $originalPertanyaan->pilihan_b_gambar,
                    'pilihan_c_gambar_raw' => $originalPertanyaan->pilihan_c_gambar,
                    'pilihan_d_gambar_raw' => $originalPertanyaan->pilihan_d_gambar
                ]);
            }

            // Duplicate all pertanyaans using raw SQL
            foreach ($pertanyaans as $pertanyaan) {
                // Use raw SQL to ensure data is copied exactly
                $insertData = [
                    'master_soal_id' => $newMasterSoal->id,
                    'urutan' => $pertanyaan->urutan,
                    'tipe_soal' => $pertanyaan->tipe_soal,
                    'pertanyaan' => $pertanyaan->pertanyaan,
                    'pertanyaan_gambar' => $pertanyaan->pertanyaan_gambar,
                    'waktu_detik' => $pertanyaan->waktu_detik,
                    'skor' => $pertanyaan->skor,
                    'jawaban_benar' => $pertanyaan->jawaban_benar,
                    'pilihan_a' => $pertanyaan->pilihan_a,
                    'pilihan_a_gambar' => $pertanyaan->pilihan_a_gambar,
                    'pilihan_b' => $pertanyaan->pilihan_b,
                    'pilihan_b_gambar' => $pertanyaan->pilihan_b_gambar,
                    'pilihan_c' => $pertanyaan->pilihan_c,
                    'pilihan_c_gambar' => $pertanyaan->pilihan_c_gambar,
                    'pilihan_d' => $pertanyaan->pilihan_d,
                    'pilihan_d_gambar' => $pertanyaan->pilihan_d_gambar,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Debug: Log data before insert
                \Log::info('Raw SQL insert data', [
                    'original_id' => $pertanyaan->id,
                    'insert_data' => $insertData
                ]);

                // Insert using raw SQL
                $newPertanyaanId = DB::table('soal_pertanyaan')->insertGetId($insertData);

                // Verify the inserted data
                $insertedData = DB::table('soal_pertanyaan')->where('id', $newPertanyaanId)->first();
                
                \Log::info('Raw SQL insert result', [
                    'new_id' => $newPertanyaanId,
                    'inserted_pertanyaan_gambar' => $insertedData->pertanyaan_gambar,
                    'inserted_pilihan_a_gambar' => $insertedData->pilihan_a_gambar,
                    'inserted_pilihan_b_gambar' => $insertedData->pilihan_b_gambar,
                    'inserted_pilihan_c_gambar' => $insertedData->pilihan_c_gambar,
                    'inserted_pilihan_d_gambar' => $insertedData->pilihan_d_gambar
                ]);
            }

            DB::commit();

            return redirect()->route('master-soal-new.edit', $newMasterSoal->id)
                ->with('success', 'Master soal berhasil diduplikasi! Anda dapat mengedit judul dan isi soal.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(MasterSoal $masterSoal)
    {
        try {
            $masterSoal->delete();

            return redirect()->route('master-soal-new.index')
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

            return redirect()->route('master-soal-new.index')
                ->with('success', "Soal berhasil {$statusText}!");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
