<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\MarketingVisitChecklist;
use App\Models\MarketingVisitChecklistItem;
use App\Models\MarketingVisitChecklistPhoto;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Exports\MarketingVisitChecklistExport;
use Maatwebsite\Excel\Facades\Excel;

class MarketingVisitChecklistController extends Controller
{
    // Template checklist point (array)
    private $template = [
        // Format: [no, category, checklist_point]
        [1, 'Branding Visual & Identitas Outlet', 'Signage depan outlet bersih dan terlihat jelas siang & malam'],
        [2, 'Branding Visual & Identitas Outlet', 'Logo dan warna outlet sesuai dengan brand guideline'],
        [3, 'Branding Visual & Identitas Outlet', 'Display promo luar (banner, X-banner, neonbox) up to date'],
        [4, 'Branding Visual & Identitas Outlet', 'Area visual sekitar outlet tidak ada yang rusak atau kusam'],
        [5, 'Material Promosi & POSM (Point of Sales Material)', 'Poster promosi terbaru sudah dipasang'],
        [6, 'Material Promosi & POSM (Point of Sales Material)', 'Display promosi bersih, Rapih, Layak dan menarik'],
        [7, 'Material Promosi & POSM (Point of Sales Material)', 'Promosi sesuai dengan periode yang berlaku'],
        [8, 'Material Promosi & POSM (Point of Sales Material)', 'Stok POSM cukup di outlet (flyers)'],
        [9, 'Digital & Customer Engagement', 'QR code loyalty/social media bisa dipindai dan berfungsi'],
        [10, 'Digital & Customer Engagement', 'Display digital promotion sinkron dengan campaign aktif'],
        [11, 'Digital & Customer Engagement', 'Tim Service aktif menawarkan promo atau program membership'],
        [12, 'Digital & Customer Engagement', 'Pelanggan aware akan campaign atau promo berjalan'],
        [13, 'Suasana & Experience Outlet', 'Suasana outlet nyaman dan sesuai konsep brand'],
        [14, 'Suasana & Experience Outlet', 'Musik dan pencahayaan sesuai waktu operasional'],
        [15, 'Aktivasi & Program Marketing', 'Ada peluang aktivasi lokal (CSR, event komunitas, dll) koordinasi dengan Tenant Relation Mall'],
        [16, 'Aktivasi & Program Marketing', 'Catat event lokal sekitar outlet untuk potensi aktivasi koordinasi dengan Tenant Relation Mall'],
        [17, 'Kompetitor & Lingkungan Sekitar', 'Ada promo menarik dari kompetitor sekitar?'],
        [18, 'Kompetitor & Lingkungan Sekitar', 'Outlet terlihat paling menonjol dibanding sekitar?'],
        [19, 'Kompetitor & Lingkungan Sekitar', 'Lingkungan sekitar outlet bersih dan aman?'],
        [20, 'Kompetitor & Lingkungan Sekitar', 'Peluang kolaborasi lokal dengan bisnis sejenis?'],
        [21, 'Kondisi Tim Outlet & Interaksi Pelanggan', 'Tim outlet memahami materi campaign/promosi? Test Outlet Leader - Staff (Minimum 2 Orang)'],
        [22, 'Kondisi Tim Outlet & Interaksi Pelanggan', 'Tim Service aktif upselling & menyampaikan promo?'],
        [23, 'Kondisi Tim Outlet & Interaksi Pelanggan', 'Interaksi tim dengan pelanggan saat menjelaskan promo?'],
        [24, 'Kondisi Tim Outlet & Interaksi Pelanggan', 'Perlu training materi promosi tambahan?'],
    ];

    public function index(Request $request)
    {
        $query = MarketingVisitChecklist::query();
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }
        if ($request->filled('visit_date')) {
            $query->where('visit_date', $request->visit_date);
        }
        $checklists = $query->with(['outlet', 'user'])
            ->latest()
            ->get();
        return Inertia::render('MarketingVisitChecklist/Index', [
            'checklists' => $checklists,
            'outlet_id' => $request->outlet_id,
            'visit_date' => $request->visit_date,
        ]);
    }

    public function create()
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();
        return Inertia::render('MarketingVisitChecklist/Form', [
            'outlets' => $outlets,
            'template' => $this->template,
            'isEdit' => false
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'visit_date' => 'required|date',
            'items' => 'required|array',
            'items.*.no' => 'required|integer',
            'items.*.category' => 'required|string',
            'items.*.checklist_point' => 'required|string',
            'items.*.checked' => 'nullable|boolean',
            'items.*.actual_condition' => 'nullable|string',
            'items.*.action' => 'nullable|string',
            'items.*.remarks' => 'nullable|string',
        ]);
        DB::beginTransaction();
        try {
            $checklist = MarketingVisitChecklist::create([
                'outlet_id' => $validated['outlet_id'],
                'visit_date' => $validated['visit_date'],
                'created_by' => Auth::id(),
            ]);
            foreach ($validated['items'] as $idx => $item) {
                $checklistItem = $checklist->items()->create($item);
                // Handle photo upload (if any)
                if ($request->hasFile("items.$idx.photos")) {
                    foreach ($request->file("items.$idx.photos") as $photo) {
                        $path = $photo->store('marketing-visit-photos', 'public');
                        $checklistItem->photos()->create(['photo_path' => $path]);
                    }
                }
            }
            DB::commit();
            return redirect()->route('marketing-visit-checklist.index')
                ->with('success', 'Checklist berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store marketing visit checklist: ' . $e->getMessage());
            return back()->with('error', 'Gagal menyimpan checklist: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $checklist = MarketingVisitChecklist::with(['outlet', 'user', 'items.photos'])->findOrFail($id);
        return Inertia::render('MarketingVisitChecklist/Show', [
            'checklist' => $checklist
        ]);
    }

    public function edit($id)
    {
        $checklist = MarketingVisitChecklist::with(['items.photos'])->findOrFail($id);
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();
        return Inertia::render('MarketingVisitChecklist/Form', [
            'checklist' => $checklist,
            'outlets' => $outlets,
            'template' => $this->template,
            'isEdit' => true
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'visit_date' => 'required|date',
            'items' => 'required|array',
            'items.*.id' => 'nullable|integer',
            'items.*.no' => 'required|integer',
            'items.*.category' => 'required|string',
            'items.*.checklist_point' => 'required|string',
            'items.*.checked' => 'nullable|boolean',
            'items.*.actual_condition' => 'nullable|string',
            'items.*.action' => 'nullable|string',
            'items.*.remarks' => 'nullable|string',
        ]);
        DB::beginTransaction();
        try {
            $checklist = MarketingVisitChecklist::findOrFail($id);
            $checklist->update([
                'outlet_id' => $validated['outlet_id'],
                'visit_date' => $validated['visit_date'],
            ]);
            // Update items
            $existingIds = $checklist->items()->pluck('id')->toArray();
            $sentIds = collect($validated['items'])->pluck('id')->filter()->toArray();
            // Delete removed items
            $toDelete = array_diff($existingIds, $sentIds);
            if ($toDelete) {
                MarketingVisitChecklistItem::whereIn('id', $toDelete)->delete();
            }
            // Upsert items
            foreach ($validated['items'] as $idx => $item) {
                if (isset($item['id'])) {
                    $checklistItem = MarketingVisitChecklistItem::find($item['id']);
                    $checklistItem->update($item);
                } else {
                    $checklistItem = $checklist->items()->create($item);
                }
                // Handle photo upload (if any)
                if ($request->hasFile("items.$idx.photos")) {
                    foreach ($request->file("items.$idx.photos") as $photo) {
                        $path = $photo->store('marketing-visit-photos', 'public');
                        $checklistItem->photos()->create(['photo_path' => $path]);
                    }
                }
            }
            DB::commit();
            return redirect()->route('marketing-visit-checklist.index')
                ->with('success', 'Checklist berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update marketing visit checklist: ' . $e->getMessage());
            return back()->with('error', 'Gagal update checklist: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $checklist = MarketingVisitChecklist::findOrFail($id);
        $checklist->delete();
        return redirect()->route('marketing-visit-checklist.index')
            ->with('success', 'Checklist berhasil dihapus');
    }

    public function export($id)
    {
        $checklist = MarketingVisitChecklist::with(['outlet', 'user', 'items.photos'])->findOrFail($id);
        return Excel::download(new MarketingVisitChecklistExport($checklist), 'marketing_visit_checklist_'.$checklist->id.'.xlsx');
    }

    // Export Excel (per checklist atau per item)
    // Akan dibuat pada tahap selanjutnya
} 