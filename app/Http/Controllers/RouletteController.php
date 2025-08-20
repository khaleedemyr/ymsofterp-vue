<?php

namespace App\Http\Controllers;

use App\Models\Roulette;
use App\Exports\RouletteTemplateExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class RouletteController extends Controller
{
    public function index(Request $request)
    {
        $query = Roulette::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $roulettes = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Roulette/Index', [
            'roulettes' => $roulettes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Roulette/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:roulettes,email',
            'no_hp' => 'nullable|string|max:15',
        ]);

        Roulette::create($request->only(['nama', 'email', 'no_hp']));

        return redirect()->route('roulette.index')->with('success', 'Data roulette berhasil ditambahkan!');
    }

    public function show(Roulette $roulette)
    {
        return Inertia::render('Roulette/Show', [
            'roulette' => $roulette,
        ]);
    }

    public function edit(Roulette $roulette)
    {
        return Inertia::render('Roulette/Edit', [
            'roulette' => $roulette,
        ]);
    }

    public function update(Request $request, Roulette $roulette)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:roulettes,email,' . $roulette->id,
            'no_hp' => 'nullable|string|max:15',
        ]);

        $roulette->update($request->only(['nama', 'email', 'no_hp']));

        return redirect()->route('roulette.index')->with('success', 'Data roulette berhasil diupdate!');
    }

    public function destroy(Roulette $roulette)
    {
        $roulette->delete();

        return redirect()->route('roulette.index')->with('success', 'Data roulette berhasil dihapus!');
    }

    public function downloadTemplate()
    {
        return Excel::download(new RouletteTemplateExport, 'template_roulette.xlsx');
    }

    public function import(Request $request)
    {
        \Log::info('RouletteController@import - Starting import');
        \Log::info('RouletteController@import - File received', [
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize()
        ]);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:2048',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);
        \Log::info('RouletteController@import - Excel data loaded', [
            'sheets_count' => count($data)
        ]);

        // Ambil sheet pertama
        $rows = $data[0] ?? [];
        if (empty($rows)) {
            \Log::error('RouletteController@import - No data found in Excel');
            return redirect()->route('roulette.index')->with('error', 'File tidak valid: Tidak ada data');
        }

        $header = array_map('trim', $rows[0] ?? []);
        \Log::info('RouletteController@import - Header found', ['header' => $header]);

        // Validasi header
        $expectedHeaders = ['Nama *', 'Email (Opsional)', 'No HP (Opsional)'];
        if (count(array_intersect($header, $expectedHeaders)) < 1) {
            \Log::error('RouletteController@import - Invalid header', ['header' => $header]);
            return redirect()->route('roulette.index')->with('error', 'File tidak valid: Header tidak sesuai template');
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach (array_slice($rows, 1) as $rowIdx => $row) {
                \Log::info('RouletteController@import - Processing row', [
                    'row_index' => $rowIdx + 2,
                    'row_data' => $row
                ]);

                // Skip baris kosong
                if (empty(array_filter($row))) {
                    \Log::info('RouletteController@import - Skipping empty row', ['row_index' => $rowIdx + 2]);
                    continue;
                }

                // Skip baris yang berisi instruksi
                $firstCell = trim($row[0] ?? '');
                if (strpos($firstCell, 'INSTRUKSI:') !== false || 
                    strpos($firstCell, '1.') !== false || 
                    strpos($firstCell, '2.') !== false || 
                    strpos($firstCell, '3.') !== false || 
                    strpos($firstCell, '4.') !== false || 
                    strpos($firstCell, '5.') !== false ||
                    strpos($firstCell, 'HAPUS') !== false ||
                    strpos($firstCell, 'CONTOH') !== false) {
                    \Log::info('RouletteController@import - Skipping instruction row', ['row_index' => $rowIdx + 2, 'content' => $firstCell]);
                    continue;
                }

                $nama = trim($row[0] ?? '');
                $email = trim($row[1] ?? '');
                $no_hp = trim($row[2] ?? '');

                // Validasi nama wajib
                if (empty($nama)) {
                    $errorMsg = "Baris " . ($rowIdx + 2) . ": Nama tidak boleh kosong";
                    $errors[] = $errorMsg;
                    $errorCount++;
                    \Log::warning('RouletteController@import - Nama kosong', ['row_index' => $rowIdx + 2]);
                    continue;
                }

                // Validasi email jika ada
                if (!empty($email)) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorMsg = "Baris " . ($rowIdx + 2) . ": Email tidak valid - " . $email;
                        $errors[] = $errorMsg;
                        $errorCount++;
                        \Log::warning('RouletteController@import - Invalid email', ['email' => $email, 'row_index' => $rowIdx + 2]);
                        continue;
                    }

                    // Cek duplikasi email
                    $existingRoulette = Roulette::where('email', $email)->first();
                    if ($existingRoulette) {
                        $errorMsg = "Baris " . ($rowIdx + 2) . ": Email sudah ada - " . $email;
                        $errors[] = $errorMsg;
                        $errorCount++;
                        \Log::warning('RouletteController@import - Email exists', ['email' => $email, 'row_index' => $rowIdx + 2]);
                        continue;
                    }
                }

                // Validasi no_hp jika ada
                if (!empty($no_hp) && strlen($no_hp) > 15) {
                    $errorMsg = "Baris " . ($rowIdx + 2) . ": No HP terlalu panjang - " . $no_hp;
                    $errors[] = $errorMsg;
                    $errorCount++;
                    \Log::warning('RouletteController@import - Phone too long', ['phone' => $no_hp, 'row_index' => $rowIdx + 2]);
                    continue;
                }

                // Simpan data
                try {
                    $roulette = new Roulette([
                        'nama' => $nama,
                        'email' => !empty($email) ? $email : null,
                        'no_hp' => !empty($no_hp) ? $no_hp : null,
                    ]);
                    $roulette->save();

                    $successCount++;
                    \Log::info('RouletteController@import - Successfully saved', [
                        'nama' => $nama,
                        'email' => $email,
                        'no_hp' => $no_hp,
                        'row_index' => $rowIdx + 2
                    ]);

                    $results[] = [
                        'row' => $rowIdx + 2,
                        'nama' => $nama,
                        'status' => 'success',
                        'message' => 'Berhasil disimpan'
                    ];

                } catch (\Exception $e) {
                    $errorMsg = "Baris " . ($rowIdx + 2) . ": Gagal menyimpan - " . $e->getMessage();
                    $errors[] = $errorMsg;
                    $errorCount++;
                    \Log::error('RouletteController@import - Save failed', [
                        'error' => $e->getMessage(),
                        'row_index' => $rowIdx + 2,
                        'data' => ['nama' => $nama, 'email' => $email, 'no_hp' => $no_hp]
                    ]);

                    $results[] = [
                        'row' => $rowIdx + 2,
                        'nama' => $nama,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            $message = "Import selesai. Berhasil: {$successCount}, Gagal: {$errorCount}";
            if (!empty($errors)) {
                $message .= "\n\nError detail:\n" . implode("\n", $errors);
            }

            \Log::info('RouletteController@import - Import completed', [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'total_errors' => count($errors)
            ]);

            return redirect()->route('roulette.index')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('RouletteController@import - Import failed', ['error' => $e->getMessage()]);
            return redirect()->route('roulette.index')->with('error', 'Gagal import file: ' . $e->getMessage());
        }
    }

    public function game()
    {
        $roulettes = Roulette::all();
        
        return Inertia::render('Roulette/Game', [
            'roulettes' => $roulettes,
        ]);
    }

    public function grid()
    {
        $roulettes = Roulette::all();
        
        return Inertia::render('Roulette/Grid', [
            'roulettes' => $roulettes,
        ]);
    }

    public function slot()
    {
        $roulettes = Roulette::all();
        
        return Inertia::render('Roulette/SlotMachine', [
            'roulettes' => $roulettes,
        ]);
    }

    public function lottery()
    {
        $roulettes = Roulette::all();
        
        return Inertia::render('Roulette/LotteryMachine', [
            'roulettes' => $roulettes,
        ]);
    }

    public function destroyAll()
    {
        try {
            $count = Roulette::count();
            Roulette::truncate();
            
            return redirect()->route('roulette.index')->with('success', "Berhasil menghapus seluruh {$count} data peserta roulette!");
        } catch (\Exception $e) {
            return redirect()->route('roulette.index')->with('error', 'Gagal menghapus seluruh data: ' . $e->getMessage());
        }
    }
} 