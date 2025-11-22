<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\JurnalGlobal;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JurnalController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = $request->input('per_page', 15);

        $query = Jurnal::with(['coaDebit', 'coaKredit', 'creator', 'outlet'])
            ->select('jurnal.*');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('jurnal.no_jurnal', 'like', "%$search%")
                  ->orWhere('jurnal.keterangan', 'like', "%$search%")
                  ->orWhereHas('coaDebit', function($q) use ($search) {
                      $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%");
                  })
                  ->orWhereHas('coaKredit', function($q) use ($search) {
                      $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%");
                  });
            });
        }

        if ($status !== 'all') {
            $query->where('jurnal.status', $status);
        }

        if ($dateFrom) {
            $query->where('jurnal.tanggal', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('jurnal.tanggal', '<=', $dateTo);
        }

        $jurnals = $query->orderBy('jurnal.tanggal', 'desc')
            ->orderBy('jurnal.no_jurnal', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Get Chart of Accounts for dropdown
        $coas = ChartOfAccount::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        // Get Outlets for dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        // Statistics
        $statistics = [
            'total' => Jurnal::count(),
            'draft' => Jurnal::where('status', 'draft')->count(),
            'posted' => Jurnal::where('status', 'posted')->count(),
            'cancelled' => Jurnal::where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Jurnal/Index', [
            'jurnals' => $jurnals,
            'coas' => $coas,
            'outlets' => $outlets,
            'statistics' => $statistics,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        $coas = ChartOfAccount::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        // Get Outlets for dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('Jurnal/Form', [
            'jurnal' => null,
            'coas' => $coas,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        // Check if multiple entries mode
        if ($request->has('entries') && is_array($request->entries)) {
            return $this->storeMultiple($request);
        }

        // Single entry mode (backward compatibility)
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string',
            'coa_debit_id' => 'required|exists:chart_of_accounts,id',
            'coa_kredit_id' => 'required|exists:chart_of_accounts,id|different:coa_debit_id',
            'jumlah_debit' => 'required|numeric|min:0',
            'jumlah_kredit' => 'required|numeric|min:0',
        ]);
        
        // Set reference sebagai null karena jurnal ini manual
        $validated['reference_type'] = null;
        $validated['reference_id'] = null;

        // Validasi jumlah debit harus sama dengan kredit
        if ($validated['jumlah_debit'] != $validated['jumlah_kredit']) {
            return back()->withErrors([
                'jumlah_debit' => 'Jumlah debit harus sama dengan jumlah kredit.',
                'jumlah_kredit' => 'Jumlah kredit harus sama dengan jumlah debit.',
            ]);
        }

        try {
            DB::beginTransaction();

            $validated['no_jurnal'] = Jurnal::generateNoJurnal();
            $validated['status'] = 'draft';
            $validated['created_by'] = auth()->id();

            $jurnal = Jurnal::create($validated);

            // Insert ke jurnal_global juga
            JurnalGlobal::create([
                'no_jurnal' => $jurnal->no_jurnal,
                'tanggal' => $jurnal->tanggal,
                'keterangan' => $jurnal->keterangan,
                'coa_debit_id' => $jurnal->coa_debit_id,
                'coa_kredit_id' => $jurnal->coa_kredit_id,
                'jumlah_debit' => $jurnal->jumlah_debit,
                'jumlah_kredit' => $jurnal->jumlah_kredit,
                'outlet_id' => $jurnal->outlet_id,
                'source_module' => 'jurnal',
                'source_id' => $jurnal->id,
                'reference_type' => $jurnal->reference_type,
                'reference_id' => $jurnal->reference_id,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('jurnal.index')
                ->with('success', 'Jurnal berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating jurnal: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal menyimpan jurnal: ' . $e->getMessage()]);
        }
    }

    private function storeMultiple(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'entries' => 'required|array|min:1',
            'entries.*.coa_debit_id' => 'required|exists:chart_of_accounts,id',
            'entries.*.coa_kredit_id' => 'required|exists:chart_of_accounts,id',
            'entries.*.jumlah' => 'required|numeric|min:0',
        ]);

        // Validasi setiap entry: debit dan kredit tidak boleh sama
        foreach ($validated['entries'] as $index => $entry) {
            if ($entry['coa_debit_id'] == $entry['coa_kredit_id']) {
                return back()->withErrors([
                    "entries.{$index}.coa_kredit_id" => "CoA debit dan kredit tidak boleh sama pada entry #" . ($index + 1)
                ]);
            }
        }

        // Validasi total debit = total kredit
        $totalDebit = collect($validated['entries'])->sum('jumlah');
        $totalKredit = collect($validated['entries'])->sum('jumlah');
        
        if ($totalDebit != $totalKredit) {
            return back()->withErrors(['error' => 'Total debit dan kredit harus sama!']);
        }

        try {
            DB::beginTransaction();

            $createdJurnals = [];
            $baseNoJurnal = Jurnal::generateNoJurnal();
            
            // Untuk multiple entries, gunakan nomor yang sama dengan suffix A, B, C, dll
            $suffixes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

            foreach ($validated['entries'] as $index => $entry) {
                // Generate no jurnal dengan suffix untuk multiple entries
                $noJurnal = $index === 0 
                    ? $baseNoJurnal 
                    : $baseNoJurnal . '-' . ($suffixes[$index] ?? $index + 1);

                $jurnalData = [
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $validated['tanggal'],
                    'keterangan' => $validated['keterangan'],
                    'coa_debit_id' => $entry['coa_debit_id'],
                    'coa_kredit_id' => $entry['coa_kredit_id'],
                    'jumlah_debit' => $entry['jumlah'],
                    'jumlah_kredit' => $entry['jumlah'],
                    'outlet_id' => $validated['outlet_id'],
                    'reference_type' => null,
                    'reference_id' => null,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ];

                $jurnal = Jurnal::create($jurnalData);
                $createdJurnals[] = $jurnal;

                // Insert ke jurnal_global juga
                JurnalGlobal::create([
                    'no_jurnal' => $jurnal->no_jurnal,
                    'tanggal' => $jurnal->tanggal,
                    'keterangan' => $jurnal->keterangan,
                    'coa_debit_id' => $jurnal->coa_debit_id,
                    'coa_kredit_id' => $jurnal->coa_kredit_id,
                    'jumlah_debit' => $jurnal->jumlah_debit,
                    'jumlah_kredit' => $jurnal->jumlah_kredit,
                    'outlet_id' => $jurnal->outlet_id,
                    'source_module' => 'jurnal',
                    'source_id' => $jurnal->id,
                    'reference_type' => $jurnal->reference_type,
                    'reference_id' => $jurnal->reference_id,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            $count = count($createdJurnals);
            return redirect()->route('jurnal.index')
                ->with('success', "Berhasil membuat {$count} jurnal!");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating multiple jurnals: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal menyimpan jurnal: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $jurnal = Jurnal::with(['coaDebit', 'coaKredit', 'outlet', 'creator'])->findOrFail($id);
        
        // Get related jurnals (same base number without suffix)
        // Pattern: JRN-YYYYMM#### or JRN-YYYYMM####-A, JRN-YYYYMM####-B, etc
        $baseNoJurnal = preg_replace('/-[A-Z]$/', '', $jurnal->no_jurnal);
        $relatedJurnals = Jurnal::with(['coaDebit', 'coaKredit'])
            ->where('tanggal', $jurnal->tanggal)
            ->where('outlet_id', $jurnal->outlet_id)
            ->where(function($q) use ($baseNoJurnal, $jurnal) {
                $q->where('no_jurnal', 'like', $baseNoJurnal . '%')
                  ->where('id', '!=', $jurnal->id);
            })
            ->orderBy('no_jurnal')
            ->get();
        
        return response()->json([
            'jurnal' => $jurnal,
            'related_jurnals' => $relatedJurnals,
        ]);
    }

    public function edit($id)
    {
        $jurnal = Jurnal::with(['coaDebit', 'coaKredit'])->findOrFail($id);
        
        // Hanya bisa edit jika status draft
        if ($jurnal->status !== 'draft') {
            return redirect()->route('jurnal.index')
                ->with('error', 'Jurnal yang sudah di-post tidak dapat diedit.');
        }

        $coas = ChartOfAccount::where('is_active', 1)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        // Get Outlets for dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('Jurnal/Form', [
            'jurnal' => $jurnal,
            'coas' => $coas,
            'outlets' => $outlets,
        ]);
    }

    public function update(Request $request, $id)
    {
        $jurnal = Jurnal::findOrFail($id);

        // Hanya bisa update jika status draft
        if ($jurnal->status !== 'draft') {
            return back()->withErrors(['error' => 'Jurnal yang sudah di-post tidak dapat diedit.']);
        }

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'coa_debit_id' => 'required|exists:chart_of_accounts,id',
            'coa_kredit_id' => 'required|exists:chart_of_accounts,id|different:coa_debit_id',
            'jumlah_debit' => 'required|numeric|min:0',
            'jumlah_kredit' => 'required|numeric|min:0',
        ]);
        
        // Set reference sebagai null karena jurnal ini manual
        $validated['reference_type'] = null;
        $validated['reference_id'] = null;

        // Validasi jumlah debit harus sama dengan kredit
        if ($validated['jumlah_debit'] != $validated['jumlah_kredit']) {
            return back()->withErrors([
                'jumlah_debit' => 'Jumlah debit harus sama dengan jumlah kredit.',
                'jumlah_kredit' => 'Jumlah kredit harus sama dengan jumlah debit.',
            ]);
        }

        try {
            DB::beginTransaction();

            $validated['updated_by'] = auth()->id();
            $jurnal->update($validated);

            // Update jurnal_global juga
            $jurnalGlobal = JurnalGlobal::where('source_module', 'jurnal')
                ->where('source_id', $jurnal->id)
                ->first();

            if ($jurnalGlobal) {
                $jurnalGlobal->update([
                    'tanggal' => $jurnal->tanggal,
                    'keterangan' => $jurnal->keterangan,
                    'coa_debit_id' => $jurnal->coa_debit_id,
                    'coa_kredit_id' => $jurnal->coa_kredit_id,
                    'jumlah_debit' => $jurnal->jumlah_debit,
                    'jumlah_kredit' => $jurnal->jumlah_kredit,
                    'outlet_id' => $jurnal->outlet_id,
                    'reference_type' => $jurnal->reference_type,
                    'reference_id' => $jurnal->reference_id,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('jurnal.index')
                ->with('success', 'Jurnal berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating jurnal: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal memperbarui jurnal: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $jurnal = Jurnal::findOrFail($id);

        // Hanya bisa delete jika status draft
        if ($jurnal->status !== 'draft') {
            return redirect()->route('jurnal.index')
                ->with('error', 'Jurnal yang sudah di-post tidak dapat dihapus.');
        }

        try {
            DB::beginTransaction();

            // Hapus dari jurnal_global juga
            JurnalGlobal::where('source_module', 'jurnal')
                ->where('source_id', $jurnal->id)
                ->delete();

            $jurnal->delete();

            DB::commit();

            return redirect()->route('jurnal.index')
                ->with('success', 'Jurnal berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting jurnal: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal menghapus jurnal: ' . $e->getMessage()]);
        }
    }

    public function post($id)
    {
        $jurnal = Jurnal::findOrFail($id);

        if ($jurnal->status !== 'draft') {
            return back()->withErrors(['error' => 'Jurnal ini sudah di-post atau dibatalkan.']);
        }

        try {
            DB::beginTransaction();

            $jurnal->update([
                'status' => 'posted',
                'updated_by' => auth()->id(),
            ]);

            // Update jurnal_global juga
            $jurnalGlobal = JurnalGlobal::where('source_module', 'jurnal')
                ->where('source_id', $jurnal->id)
                ->first();

            if ($jurnalGlobal) {
                $jurnalGlobal->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('jurnal.index')
                ->with('success', 'Jurnal berhasil di-post!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error posting jurnal: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal mem-post jurnal: ' . $e->getMessage()]);
        }
    }

    public function cancel($id)
    {
        $jurnal = Jurnal::findOrFail($id);

        if ($jurnal->status === 'cancelled') {
            return back()->withErrors(['error' => 'Jurnal ini sudah dibatalkan.']);
        }

        try {
            DB::beginTransaction();

            $jurnal->update([
                'status' => 'cancelled',
                'updated_by' => auth()->id(),
            ]);

            // Update jurnal_global juga
            $jurnalGlobal = JurnalGlobal::where('source_module', 'jurnal')
                ->where('source_id', $jurnal->id)
                ->first();

            if ($jurnalGlobal) {
                $jurnalGlobal->update([
                    'status' => 'cancelled',
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('jurnal.index')
                ->with('success', 'Jurnal berhasil dibatalkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error cancelling jurnal: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal membatalkan jurnal: ' . $e->getMessage()]);
        }
    }
}

