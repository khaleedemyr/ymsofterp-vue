<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    protected $filters;
    public $fileName = 'data_karyawan.xlsx';

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        $this->fileName = 'data_karyawan_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = User::query()
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->select(
                'users.*',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_outlet.nama_outlet',
                'tbl_data_divisi.nama_divisi'
            );

        // Apply filters
        if (isset($this->filters['search']) && $this->filters['search']) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%$search%")
                  ->orWhere('users.nik', 'like', "%$search%")
                  ->orWhere('users.email', 'like', "%$search%")
                  ->orWhere('users.no_hp', 'like', "%$search%")
                  ->orWhere('users.no_ktp', 'like', "%$search%")
                  ->orWhere('users.nama_panggilan', 'like', "%$search%");
            });
        }

        if (isset($this->filters['outlet_id']) && $this->filters['outlet_id']) {
            $query->where('users.id_outlet', $this->filters['outlet_id']);
        }

        if (isset($this->filters['division_id']) && $this->filters['division_id']) {
            $query->where('users.division_id', $this->filters['division_id']);
        }

        if (isset($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('users.status', $this->filters['status']);
        }

        return $query->orderBy('users.nama_lengkap')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'NIK',
            'Nama Lengkap',
            'Nama Panggilan',
            'Email',
            'No. HP',
            'No. KTP',
            'Jabatan',
            'Divisi',
            'Outlet',
            'Status',
            'Tanggal Masuk',
            'Tanggal Keluar',
            'Alamat',
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Agama',
            'Status Kawin',
            'Tanggal Dibuat',
            'Tanggal Diupdate'
        ];
    }

    /**
     * @param mixed $user
     * @return array
     */
    public function map($user): array
    {
        return [
            $user->id,
            $user->nik,
            $user->nama_lengkap,
            $user->nama_panggilan,
            $user->email,
            $user->no_hp,
            $user->no_ktp,
            $user->nama_jabatan,
            $user->nama_divisi,
            $user->nama_outlet,
            $this->getStatusText($user->status),
            $user->tanggal_masuk ? date('d/m/Y', strtotime($user->tanggal_masuk)) : '',
            $user->tanggal_keluar ? date('d/m/Y', strtotime($user->tanggal_keluar)) : '',
            $user->alamat,
            $user->tanggal_lahir ? date('d/m/Y', strtotime($user->tanggal_lahir)) : '',
            $user->jenis_kelamin,
            $user->agama,
            $user->status_kawin,
            $user->created_at ? date('d/m/Y H:i', strtotime($user->created_at)) : '',
            $user->updated_at ? date('d/m/Y H:i', strtotime($user->updated_at)) : '',
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 10, // ID
            'B' => 15, // NIK
            'C' => 25, // Nama Lengkap
            'D' => 20, // Nama Panggilan
            'E' => 30, // Email
            'F' => 15, // No. HP
            'G' => 20, // No. KTP
            'H' => 25, // Jabatan
            'I' => 20, // Divisi
            'J' => 25, // Outlet
            'K' => 10, // Status
            'L' => 15, // Tanggal Masuk
            'M' => 15, // Tanggal Keluar
            'N' => 40, // Alamat
            'O' => 15, // Tanggal Lahir
            'P' => 15, // Jenis Kelamin
            'Q' => 15, // Agama
            'R' => 15, // Status Kawin
            'S' => 20, // Tanggal Dibuat
            'T' => 20, // Tanggal Diupdate
        ];
    }

    /**
     * Get status text
     */
    private function getStatusText($status)
    {
        switch ($status) {
            case 'A':
                return 'Aktif';
            case 'N':
                return 'Tidak Aktif';
            case 'B':
                return 'Baru';
            default:
                return $status;
        }
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        try {
            return Excel::download($this, $this->fileName);
        } catch (\Exception $e) {
            \Log::error('UsersExport toResponse error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate file Excel'], 500);
        }
    }
}
