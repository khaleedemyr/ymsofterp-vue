<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Outlet;
use App\Models\Jabatan;
use App\Models\Divisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class EmployeeUploadController extends Controller
{

    public function upload(Request $request)
    {
        \Log::info('Employee upload started', ['file_name' => $request->file('file')?->getClientOriginalName()]);
        
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB max
            ]);
            
            \Log::info('File validation passed');
        } catch (\Exception $e) {
            \Log::error('File validation failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Validasi file gagal: ' . $e->getMessage());
        }

        try {
            DB::beginTransaction();
            \Log::info('Database transaction started');

            $import = new EmployeeImport();
            Excel::import($import, $request->file('file'));
            
            \Log::info('Excel import completed', [
                'total_processed' => $import->getRowCount(),
                'successful_updates' => $import->getSuccessCount(),
                'errors_count' => count($import->getErrors())
            ]);

            DB::commit();
            \Log::info('Database transaction committed');

            $response = [
                'message' => 'Data karyawan berhasil diupload!',
                'details' => [
                    'total_processed' => $import->getRowCount(),
                    'successful_updates' => $import->getSuccessCount(),
                    'errors' => $import->getErrors()
                ]
            ];
            
            \Log::info('Returning success response', $response);
            return back()->with('success', $response);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Employee upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorResponse = 'Gagal mengupload data: ' . $e->getMessage();
            \Log::info('Returning error response', ['error' => $errorResponse]);
            return back()->with('error', $errorResponse);
        }
    }

    public function downloadTemplate()
    {
        $templateData = [
            ['ID', 'NIK', 'NO KTP', 'Nama Lengkap', 'Email', 'QR Code Outlet', 'Jabatan', 'Divisi', 'Cuti', 'Status'],
            ['1629', '241208', '3273012904910005', 'Davit ramdani', 'ramdanidavit37@gmail.com', 'AG001', 'Cook Steakhouse Bandung', 'OPERATION - Food & Beverage', '0', 'A'],
            ['856', '240448', '', 'Panji', 'enjuelsenjos@gmail.com', 'AG002', 'Demi Chef Food Court Bandung', 'OPERATION - Steakhouse Bandung', '5', 'N']
        ];

        return Excel::download(new EmployeeTemplateExport($templateData), 'template_employee_upload.xlsx');
    }
}

class EmployeeImport implements ToCollection, WithHeadingRow, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    private $rowCount = 0;
    private $successCount = 0;
    private $importErrors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $this->rowCount++;
            \Log::info("Processing row {$this->rowCount}", ['row_data' => $row->toArray()]);

            try {
                // Validate required fields from Excel only
                if (empty($row['id'])) {
                    $this->importErrors[] = "Row {$this->rowCount}: ID harus diisi";
                    continue;
                }
                
                if (empty($row['nik'])) {
                    $this->importErrors[] = "Row {$this->rowCount}: NIK harus diisi";
                    continue;
                }
                
                if (empty($row['nama_lengkap'])) {
                    $this->importErrors[] = "Row {$this->rowCount}: Nama Lengkap harus diisi";
                    continue;
                }

                // Find user by ID using where clause
                $user = User::where('id', $row['id'])->first();
                if (!$user) {
                    $errorMsg = "Row {$this->rowCount}: User dengan ID {$row['id']} tidak ditemukan";
                    $this->importErrors[] = $errorMsg;
                    \Log::warning($errorMsg);
                    continue;
                }
            \Log::info("User found for ID {$row['id']}", ['user_nik' => $user->nik, 'user_name' => $user->nama_lengkap]);

            // Validate user exists (only check if user with this ID exists)
            // No need to validate existing data integrity, just check if user exists

            // Get outlet if provided in Excel
            $outlet = null;
            if (!empty($row['qr_code_outlet'])) {
                $outlet = Outlet::where('qr_code', $row['qr_code_outlet'])->first();
                if (!$outlet) {
                    // Log warning but continue processing (don't update outlet)
                    \Log::warning("Row {$this->rowCount}: Outlet dengan QR Code '{$row['qr_code_outlet']}' tidak ditemukan, skip outlet update");
                    $this->importErrors[] = "Row {$this->rowCount}: Outlet dengan QR Code '{$row['qr_code_outlet']}' tidak ditemukan (outlet tidak diupdate)";
                }
            }

            // Get jabatan if provided in Excel
            $jabatan = null;
            if (!empty($row['jabatan'])) {
                $jabatan = Jabatan::where('nama_jabatan', 'like', '%' . $row['jabatan'] . '%')
                    ->where('status', 'A')
                    ->first();
                if (!$jabatan) {
                    // Log warning but continue processing (don't update jabatan)
                    \Log::warning("Row {$this->rowCount}: Jabatan '{$row['jabatan']}' tidak ditemukan, skip jabatan update");
                    $this->importErrors[] = "Row {$this->rowCount}: Jabatan '{$row['jabatan']}' tidak ditemukan (jabatan tidak diupdate)";
                }
            }

            // Get divisi if provided in Excel
            $divisi = null;
            if (!empty($row['divisi'])) {
                $divisi = Divisi::where('nama_divisi', 'like', '%' . $row['divisi'] . '%')
                    ->where('status', 'A')
                    ->first();
                if (!$divisi) {
                    // Log warning but continue processing (don't update divisi)
                    \Log::warning("Row {$this->rowCount}: Divisi '{$row['divisi']}' tidak ditemukan, skip divisi update");
                    $this->importErrors[] = "Row {$this->rowCount}: Divisi '{$row['divisi']}' tidak ditemukan (divisi tidak diupdate)";
                }
            }

            // Prepare update data
            $updateData = [
                'nik' => $row['nik'],
                'nama_lengkap' => $row['nama_lengkap'],
                'email' => $row['email'] ?? $user->email,
            ];

            // Add optional fields if provided
            if (!empty($row['no_ktp'])) {
                $updateData['no_ktp'] = $row['no_ktp'];
            }

            // Only add to updateData if data is found
            if ($outlet) {
                $updateData['id_outlet'] = $outlet->id_outlet;
                \Log::info("Row {$this->rowCount}: Outlet found and will be updated", ['outlet_id' => $outlet->id_outlet]);
            }

            if ($jabatan) {
                $updateData['id_jabatan'] = $jabatan->id_jabatan;
                \Log::info("Row {$this->rowCount}: Jabatan found and will be updated", ['jabatan_id' => $jabatan->id_jabatan]);
            }

            if ($divisi) {
                $updateData['division_id'] = $divisi->id;
                \Log::info("Row {$this->rowCount}: Divisi found and will be updated", ['divisi_id' => $divisi->id]);
            }

            if (!empty($row['cuti']) && is_numeric($row['cuti'])) {
                $updateData['cuti'] = (int) $row['cuti'];
            }

            // Update status only if provided in Excel
            if (!empty($row['status'])) {
                if (in_array(strtoupper($row['status']), ['A', 'N'])) {
                    $updateData['status'] = strtoupper($row['status']);
                } else {
                    $this->importErrors[] = "Row {$this->rowCount}: Status '{$row['status']}' tidak valid. Gunakan 'A' (Aktif) atau 'N' (Non-Aktif)";
                    continue;
                }
            }

            // Update user
            try {
                \Log::info("Updating user ID {$user->id}", ['update_data' => $updateData]);
                $user->update($updateData);
                $this->successCount++;
                \Log::info("Row {$this->rowCount}: User updated successfully", ['user_id' => $user->id, 'updated_fields' => array_keys($updateData)]);
            } catch (\Exception $e) {
                $errorMsg = "Row {$this->rowCount}: Gagal update user - " . $e->getMessage();
                $this->importErrors[] = $errorMsg;
                \Log::error($errorMsg, ['user_id' => $user->id, 'update_data' => $updateData, 'exception' => $e->getTraceAsString()]);
                continue;
            }

            } catch (\Exception $e) {
                $this->importErrors[] = "Row {$this->rowCount}: " . $e->getMessage();
                continue;
            }
        }
    }


    public function getRowCount()
    {
        return $this->rowCount;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getErrors()
    {
        return $this->importErrors;
    }
}

class EmployeeTemplateExport implements \Maatwebsite\Excel\Concerns\FromArray
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }
}
