<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Supplier;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('contact_person', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $suppliers = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:suppliers,code',
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'npwp' => 'nullable|string|max:30',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'payment_term' => 'nullable|string|max:50',
            'payment_days' => 'nullable|integer',
            'status' => 'required|in:active,inactive',
        ]);
        $supplier = Supplier::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'suppliers',
            'description' => 'Menambahkan supplier baru: ' . $supplier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $supplier->toArray(),
        ]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:suppliers,code,' . $id,
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'npwp' => 'nullable|string|max:30',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'payment_term' => 'nullable|string|max:50',
            'payment_days' => 'nullable|integer',
            'status' => 'required|in:active,inactive',
        ]);
        $supplier = Supplier::findOrFail($id);
        $oldData = $supplier->toArray();
        $supplier->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'suppliers',
            'description' => 'Mengupdate supplier: ' . $supplier->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $supplier->fresh()->toArray(),
        ]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil diupdate!');
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $oldData = $supplier->toArray();
        $supplier->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'suppliers',
            'description' => 'Menonaktifkan supplier: ' . $supplier->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $supplier->fresh()->toArray(),
        ]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }

    public function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        // Sheet 1: Instruction
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Instruction');
        $sheet1->setCellValue('A1', 'INSTRUCTION IMPORT SUPPLIER');
        $sheet1->setCellValue('A3', '1. Isi data supplier pada sheet "Data".');
        $sheet1->setCellValue('A4', '2. Kolom code dan status tidak perlu diisi, akan diisi otomatis oleh sistem.');
        $sheet1->setCellValue('A5', '3. Email harus unik, tidak boleh sama dengan supplier lain.');
        $sheet1->setCellValue('A6', '4. Kolom payment_days diisi angka (boleh kosong jika tidak ada).');
        $sheet1->setCellValue('A7', '5. Contoh pengisian:');
        $sheet1->setCellValue('A9', 'name');
        $sheet1->setCellValue('B9', 'contact_person');
        $sheet1->setCellValue('C9', 'phone');
        $sheet1->setCellValue('D9', 'email');
        $sheet1->setCellValue('E9', 'address');
        $sheet1->setCellValue('F9', 'city');
        $sheet1->setCellValue('G9', 'province');
        $sheet1->setCellValue('H9', 'postal_code');
        $sheet1->setCellValue('I9', 'npwp');
        $sheet1->setCellValue('J9', 'bank_name');
        $sheet1->setCellValue('K9', 'bank_account_number');
        $sheet1->setCellValue('L9', 'bank_account_name');
        $sheet1->setCellValue('M9', 'payment_term');
        $sheet1->setCellValue('N9', 'payment_days');
        $sheet1->setCellValue('A10', 'PT Contoh Supplier');
        $sheet1->setCellValue('B10', 'Budi');
        $sheet1->setCellValue('C10', '08123456789');
        $sheet1->setCellValue('D10', 'supplier@email.com');
        $sheet1->setCellValue('E10', 'Jl. Contoh No.1');
        $sheet1->setCellValue('F10', 'Jakarta');
        $sheet1->setCellValue('G10', 'DKI Jakarta');
        $sheet1->setCellValue('H10', '12345');
        $sheet1->setCellValue('I10', '1234567890');
        $sheet1->setCellValue('J10', 'BCA');
        $sheet1->setCellValue('K10', '1234567890');
        $sheet1->setCellValue('L10', 'PT Contoh Supplier');
        $sheet1->setCellValue('M10', '30 hari');
        $sheet1->setCellValue('N10', '30');

        // Sheet 2: Data
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Data');
        $headers = [
            'name', 'contact_person', 'phone', 'email', 'address', 'city', 'province', 'postal_code', 'npwp',
            'bank_name', 'bank_account_number', 'bank_account_name', 'payment_term', 'payment_days'
        ];
        foreach ($headers as $i => $header) {
            $sheet2->setCellValueByColumnAndRow($i + 1, 1, $header);
        }
        // Set active to sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $filename = 'template_import_supplier.xlsx';
        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Remove header row
        array_shift($rows);

        $preview = [];
        $errors = [];
        $rowNumber = 2; // Start from row 2 (after header)

        foreach ($rows as $row) {
            $data = [
                'name' => $row[0] ?? '',
                'contact_person' => $row[1] ?? '',
                'phone' => $row[2] ?? '',
                'email' => $row[3] ?? '',
                'address' => $row[4] ?? '',
                'city' => $row[5] ?? '',
                'province' => $row[6] ?? '',
                'postal_code' => $row[7] ?? '',
                'npwp' => $row[8] ?? '',
                'bank_name' => $row[9] ?? '',
                'bank_account_number' => $row[10] ?? '',
                'bank_account_name' => $row[11] ?? '',
                'payment_term' => $row[12] ?? '',
                'payment_days' => $row[13] ?? '',
            ];

            // Validate data
            $validator = \Validator::make($data, [
                'name' => 'required|string|max:100',
                'contact_person' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:30',
                'email' => 'nullable|email|max:100|unique:suppliers,email',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'npwp' => 'nullable|string|max:30',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_account_name' => 'nullable|string|max:100',
                'payment_term' => 'nullable|string|max:50',
                'payment_days' => 'nullable|integer',
            ]);

            $rowErrors = [];
            if ($validator->fails()) {
                $rowErrors = $validator->errors()->all();
            }

            $preview[] = [
                'row_number' => $rowNumber,
                'data' => $data,
                'errors' => $rowErrors,
                'is_valid' => empty($rowErrors)
            ];

            $rowNumber++;
        }

        return response()->json([
            'preview' => $preview,
            'total_rows' => count($rows),
            'valid_rows' => count(array_filter($preview, fn($row) => $row['is_valid'])),
            'invalid_rows' => count(array_filter($preview, fn($row) => !$row['is_valid']))
        ]);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Remove header row
        array_shift($rows);

        $results = [];
        $rowNumber = 2; // Start from row 2 (after header)
        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            $data = [
                'name' => $row[0] ?? '',
                'contact_person' => $row[1] ?? '',
                'phone' => $row[2] ?? '',
                'email' => $row[3] ?? '',
                'address' => $row[4] ?? '',
                'city' => $row[5] ?? '',
                'province' => $row[6] ?? '',
                'postal_code' => $row[7] ?? '',
                'npwp' => $row[8] ?? '',
                'bank_name' => $row[9] ?? '',
                'bank_account_number' => $row[10] ?? '',
                'bank_account_name' => $row[11] ?? '',
                'payment_term' => $row[12] ?? '',
                'payment_days' => $row[13] ?? '',
                'status' => 'active',
                'code' => 'SUP' . str_pad(Supplier::max('id') + 1, 5, '0', STR_PAD_LEFT)
            ];

            try {
                // Validate data
                $validator = \Validator::make($data, [
                    'name' => 'required|string|max:100',
                    'contact_person' => 'nullable|string|max:100',
                    'phone' => 'nullable|string|max:30',
                    'email' => 'nullable|email|max:100|unique:suppliers,email',
                    'address' => 'nullable|string',
                    'city' => 'nullable|string|max:100',
                    'province' => 'nullable|string|max:100',
                    'postal_code' => 'nullable|string|max:20',
                    'npwp' => 'nullable|string|max:30',
                    'bank_name' => 'nullable|string|max:100',
                    'bank_account_number' => 'nullable|string|max:50',
                    'bank_account_name' => 'nullable|string|max:100',
                    'payment_term' => 'nullable|string|max:50',
                    'payment_days' => 'nullable|integer',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(implode(', ', $validator->errors()->all()));
                }

                // Create supplier
                $supplier = Supplier::create($data);

                // Log activity
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'activity_type' => 'create',
                    'module' => 'suppliers',
                    'description' => 'Import supplier: ' . $supplier->name,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => $supplier->toArray(),
                ]);

                $results[] = [
                    'row_number' => $rowNumber,
                    'status' => 'success',
                    'message' => 'Supplier berhasil diimport',
                    'data' => $data
                ];
                $successCount++;
            } catch (\Exception $e) {
                \Log::error('Import Supplier Error', [
                    'row_number' => $rowNumber,
                    'message' => $e->getMessage(),
                    'data' => $data,
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[] = [
                    'row_number' => $rowNumber,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'data' => $data
                ];
                $errorCount++;
            }

            $rowNumber++;
        }

        return response()->json([
            'results' => $results,
            'total_rows' => count($rows),
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
    }
} 