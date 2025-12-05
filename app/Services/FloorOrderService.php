<?php

namespace App\Services;

use PDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class FloorOrderService
{
    public function generateSupplierFONumber($supplierId)
    {
        $supplier = \DB::table('suppliers')->where('id', $supplierId)->first();
        if (!$supplier) {
            throw new \Exception('Supplier tidak ditemukan');
        }

        $prefix = 'RO-SUPP';
        $supplierCode = strtoupper(substr($supplier->name, 0, 3));
        $date = now()->format('ymd');
        
        // Cari nomor terakhir untuk supplier ini hari ini
        $lastNumber = \DB::table('food_floor_order_supplier_headers')
            ->where('supplier_fo_number', 'like', "{$prefix}{$supplierCode}{$date}%")
            ->orderBy('supplier_fo_number', 'desc')
            ->first();

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber->supplier_fo_number, -3);
            $newSequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSequence = '001';
        }

        return "{$prefix}{$supplierCode}{$date}{$newSequence}";
    }

    public function generateFONumber()
    {
        $prefix = 'FO';
        $date = now()->format('ymd');
        
        // Cari nomor terakhir untuk hari ini
        $lastNumber = \DB::table('food_floor_orders')
            ->where('order_number', 'like', "{$prefix}{$date}%")
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber->order_number, -3);
            $newSequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSequence = '001';
        }

        return "{$prefix}{$date}{$newSequence}";
    }

    public function generatePDF($supplierFoNumber, $supplier, $items, $outlet)
    {
        $pdf = PDF::loadView('pdf.floor-order-supplier', [
            'supplierFoNumber' => $supplierFoNumber,
            'supplier' => $supplier,
            'items' => $items,
            'outlet' => $outlet,
            'date' => now()->format('d/m/Y'),
        ]);

        $path = 'pdfs/' . $supplierFoNumber . '.pdf';
        Storage::put('public/' . $path, $pdf->output());
        
        return $path;
    }
} 