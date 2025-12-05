<?php
// app/Http/Controllers/MaintenanceBiddingController.php

namespace App\Http\Controllers;

use App\Models\MaintenanceBiddingSession;
use App\Models\MaintenanceBiddingGroup;
use App\Models\MaintenanceBiddingGroupItem;
use App\Models\MaintenanceBiddingGroupSupplier;
use App\Models\MaintenanceBiddingQuote;
use App\Models\MaintenanceBiddingAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;

class MaintenanceBiddingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:maintenance_tasks,id',
            'groups' => 'required|array',
            'groups.*.name' => 'required|string',
            'groups.*.items' => 'required|array',
            'groups.*.suppliers' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            // Create bidding session
            $bidding = MaintenanceBiddingSession::create([
                'task_id' => $request->task_id,
                'status' => 'DRAFT',
                'created_by' => auth()->id(),
            ]);

            // Create groups
            foreach ($request->groups as $groupData) {
                $group = MaintenanceBiddingGroup::create([
                    'bidding_session_id' => $bidding->id,
                    'nama' => $groupData['name'],
                    'status' => 'DRAFT',
                ]);

                // Add items to group
                foreach ($groupData['items'] as $itemId) {
                    MaintenanceBiddingGroupItem::create([
                        'bidding_group_id' => $group->id,
                        'pr_item_id' => $itemId,
                    ]);
                }

                // Add suppliers to group
                foreach ($groupData['suppliers'] as $supplierId) {
                    MaintenanceBiddingGroupSupplier::create([
                        'bidding_group_id' => $group->id,
                        'supplier_id' => $supplierId,
                        'status' => 'INVITED',
                    ]);
                }
            }

            DB::commit();
            return response()->json($bidding->load('groups.items', 'groups.suppliers'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat bidding session'], 500);
        }
    }

    public function show($id)
    {
        $bidding = MaintenanceBiddingSession::with([
            'groups.items.prItem',
            'groups.suppliers',
            'groups.quotes.attachments',
        ])->findOrFail($id);

        return response()->json($bidding);
    }

    public function uploadQuote(Request $request, $groupId)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'quote_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Store file
            $path = $request->file('quote_file')->store('bidding-quotes');

            // Create quote
            $quote = MaintenanceBiddingQuote::create([
                'bidding_group_id' => $groupId,
                'supplier_id' => $request->supplier_id,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Create attachment
            MaintenanceBiddingAttachment::create([
                'quote_id' => $quote->id,
                'nama_file' => $request->file('quote_file')->getClientOriginalName(),
                'path_file' => $path,
                'tipe_file' => $request->file('quote_file')->getMimeType(),
                'ukuran_file' => $request->file('quote_file')->getSize(),
            ]);

            // Update supplier status
            MaintenanceBiddingGroupSupplier::where([
                'bidding_group_id' => $groupId,
                'supplier_id' => $request->supplier_id,
            ])->update(['status' => 'QUOTED']);

            DB::commit();
            return response()->json(['message' => 'Berhasil upload penawaran']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal upload penawaran'], 500);
        }
    }

    public function storeQuotes(Request $request, $groupId)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'delivery_time' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'quotes' => 'required|array',
            'quotes.*.item_id' => 'required|exists:maintenance_purchase_requisition_items,id',
            'quotes.*.price' => 'required|numeric|min:0',
            'quotes.*.subtotal' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Create or update quotes
            foreach ($request->quotes as $quoteData) {
                MaintenanceBiddingQuote::updateOrCreate(
                    [
                        'bidding_group_id' => $groupId,
                        'supplier_id' => $request->supplier_id,
                        'pr_item_id' => $quoteData['item_id'],
                    ],
                    [
                        'harga' => $quoteData['price'],
                        'subtotal' => $quoteData['subtotal'],
                        'waktu_pengiriman' => $request->delivery_time,
                        'catatan' => $request->notes,
                        'created_by' => auth()->id(),
                    ]
                );
            }

            // Update supplier status
            MaintenanceBiddingGroupSupplier::where([
                'bidding_group_id' => $groupId,
                'supplier_id' => $request->supplier_id,
            ])->update(['status' => 'QUOTED']);

            DB::commit();
            return response()->json(['message' => 'Berhasil menyimpan penawaran']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan penawaran'], 500);
        }
    }

    public function exportForm($biddingId, $groupId)
    {
        $group = MaintenanceBiddingGroup::with(['items.prItem', 'session'])
            ->findOrFail($groupId);

        $pdf = PDF::loadView('pdf.bidding-form', [
            'group' => $group,
            'date' => now()->format('d/m/Y'),
        ]);

        return $pdf->download('bidding-form-' . $group->nama . '.pdf');
    }

    public function storeBiddingOffer(Request $request)
    {
        $supplier_id = $request->input('supplier_id');
        $offers = $request->input('offers', []);
        if (is_string($offers)) {
            $offers = json_decode($offers, true);
        }
        $file = $request->file('file');

        $file_path = null;
        if ($file) {
            $file_path = $file->store('bidding_offers', 'public');
        }

        foreach ($offers as $pr_item_id => $price) {
            \DB::table('maintenance_bidding_offers')->insert([
                'pr_item_id' => $pr_item_id,
                'supplier_id' => $supplier_id,
                'price' => $price,
                'file_path' => $file_path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
