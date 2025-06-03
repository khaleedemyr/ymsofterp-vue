<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        // Validasi lengkap sesuai kebutuhan
        $validated = $request->validate([
            // 'nik' dihapus dari validasi karena akan digenerate otomatis
            'no_ktp' => 'required|string|max:50|unique:users,no_ktp',
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'jenis_kelamin' => 'required|in:laki-laki,perempuan',
            'tempat_lahir' => 'required|string|max:100',
            'tanggal_lahir' => 'required|date',
            'agama' => 'required|string|max:50',
            'status_pernikahan' => 'required|string|max:50',
            'alamat' => 'required|string',
            'alamat_ktp' => 'required|string',
            'golongan_darah' => 'nullable|string|max:5',
            'no_hp' => 'required|string|max:20',
            'nama_kontak_darurat' => 'required|string|max:255',
            'no_hp_kontak_darurat' => 'required|string|max:20',
            'hubungan_kontak_darurat' => 'required|string|max:50',
            'jumlah_anak' => 'required|string',
            'nomor_kk' => 'required|string',
            'nama_rekening' => 'required|string',
            'no_rekening' => 'required|string',
            'npwp_number' => 'nullable|string',
            'bpjs_health_number' => 'nullable|string',
            'bpjs_employment_number' => 'nullable|string',
            'last_education' => 'required|string',
            'name_school_college' => 'required|string',
            'school_college_major' => 'required|string',
            'position' => 'required|string',
            // File upload
            'foto_ktp' => 'nullable|file|image|max:1024',
            'foto_kk' => 'nullable|file|image|max:1024',
            'upload_latest_color_photo' => 'nullable|file|image|max:10240',
            'avatar' => 'nullable|file|image|max:2048',
            'pin_pos' => 'nullable|string|max:10',
            'work_start_date' => 'nullable|date',
        ]);

        // Handle file upload
        $foto_ktp_path = $request->hasFile('foto_ktp') ? $request->file('foto_ktp')->store('ktp', 'public') : null;
        $foto_kk_path = $request->hasFile('foto_kk') ? $request->file('foto_kk')->store('kk', 'public') : null;
        $latest_photo_path = $request->hasFile('upload_latest_color_photo') ? $request->file('upload_latest_color_photo')->store('latest_photo', 'public') : null;
        $avatar_path = $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null;

        // Simpan user
        $user = User::create([
            'nik' => $this->generateNik(),
            'no_ktp' => $validated['no_ktp'],
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'hint_password' => $validated['password'],
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'tempat_lahir' => $validated['tempat_lahir'],
            'tanggal_lahir' => $validated['tanggal_lahir'],
            'agama' => $validated['agama'],
            'status_pernikahan' => $validated['status_pernikahan'],
            'alamat' => $validated['alamat'],
            'alamat_ktp' => $validated['alamat_ktp'],
            'golongan_darah' => $validated['golongan_darah'] ?? null,
            'no_hp' => $validated['no_hp'],
            'nama_kontak_darurat' => $validated['nama_kontak_darurat'],
            'no_hp_kontak_darurat' => $validated['no_hp_kontak_darurat'],
            'hubungan_kontak_darurat' => $validated['hubungan_kontak_darurat'],
            'jumlah_anak' => $validated['jumlah_anak'],
            'nomor_kk' => $validated['nomor_kk'],
            'nama_rekening' => $validated['nama_rekening'],
            'no_rekening' => $validated['no_rekening'],
            'npwp_number' => $validated['npwp_number'] ?? null,
            'bpjs_health_number' => $validated['bpjs_health_number'] ?? null,
            'bpjs_employment_number' => $validated['bpjs_employment_number'] ?? null,
            'last_education' => $validated['last_education'],
            'name_school_college' => $validated['name_school_college'],
            'school_college_major' => $validated['school_college_major'],
            'position' => $validated['position'],
            'foto_ktp' => $foto_ktp_path,
            'foto_kk' => $foto_kk_path,
            'upload_latest_color_photo' => $latest_photo_path,
            'imei' => $request->input('imei'),
            'avatar' => $avatar_path,
            'status' => 'B',
            'pin_pos' => $validated['pin_pos'] ?? null,
            'work_start_date' => $validated['work_start_date'] ?? null,
            'tanggal_masuk' => $validated['work_start_date'] ?? null,
        ]);

        return response()->json(['success' => true, 'user' => $user]);
    }

    // Fungsi untuk generate NIK: 4 digit sequence + 2 digit tahun
    private function generateNik()
    {
        $lastUser = User::orderBy('id', 'desc')->first();
        $year = date('y'); // 2 digit tahun
        $lastSequence = $lastUser ? intval(substr($lastUser->nik, 0, -2)) : 251609;
        $newSequence = $lastSequence + 1;
        return strval($newSequence) . $year;
    }
}
