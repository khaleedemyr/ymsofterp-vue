<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        
        $request->validate([
            // Personal Info
            'nama_lengkap' => 'required|string|max:255',
            'nama_panggilan' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'no_hp' => 'required|string|max:15',
            'jenis_kelamin' => 'required|string|max:1',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'suku' => 'required|string|max:50',
            'agama' => 'required|string|max:50',
            'status_pernikahan' => 'required|string|max:50',
            'golongan_darah' => 'required|string|max:5',
            
            // Address
            'alamat' => 'required|string',
            'alamat_ktp' => 'required|string',
            
            // Work Info (without jabatan, outlet, divisi)
            
            // Financial
            'nama_rekening' => 'required|string|max:255',
            'no_rekening' => 'required|string|max:50',
            'npwp_number' => 'required|string|max:100',
            'bpjs_health_number' => 'required|string|max:100',
            'bpjs_employment_number' => 'required|string|max:100',
            
            // Education
            'last_education' => 'required|string|max:100',
            'name_school_college' => 'required|string|max:255',
            'school_college_major' => 'required|string|max:255',
            
            // Emergency Contact
            'nama_kontak_darurat' => 'required|string|max:255',
            'no_hp_kontak_darurat' => 'required|string|max:15',
            'hubungan_kontak_darurat' => 'required|string|max:50',
            
            // Documents
            'no_ktp' => 'required|string|max:50',
            'nomor_kk' => 'required|string|max:50',
            
            // Files
            'avatar' => 'nullable|image|max:2048',
            'foto_ktp' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            'foto_kk' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            'upload_latest_color_photo' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Generate NIK
        $nik = $this->generateNIK();

        // Prepare user data
        $userData = [
            'nik' => $nik,
            'nama_lengkap' => $request->nama_lengkap,
            'nama_panggilan' => $request->nama_panggilan,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_hp' => $request->no_hp,
            'jenis_kelamin' => $request->jenis_kelamin,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'suku' => $request->suku,
            'agama' => $request->agama,
            'status_pernikahan' => $request->status_pernikahan,
            'golongan_darah' => $request->golongan_darah,
            'alamat' => $request->alamat,
            'alamat_ktp' => $request->alamat_ktp,
            'nama_rekening' => $request->nama_rekening,
            'no_rekening' => $request->no_rekening,
            'npwp_number' => $request->npwp_number,
            'bpjs_health_number' => $request->bpjs_health_number,
            'bpjs_employment_number' => $request->bpjs_employment_number,
            'last_education' => $request->last_education,
            'name_school_college' => $request->name_school_college,
            'school_college_major' => $request->school_college_major,
            'nama_kontak_darurat' => $request->nama_kontak_darurat,
            'no_hp_kontak_darurat' => $request->no_hp_kontak_darurat,
            'hubungan_kontak_darurat' => $request->hubungan_kontak_darurat,
            'no_ktp' => $request->no_ktp,
            'nomor_kk' => $request->nomor_kk,
            'status' => 'B', // New user status
        ];

        // Handle file uploads
        if ($request->hasFile('avatar')) {
            $userData['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }
        if ($request->hasFile('foto_ktp')) {
            $userData['foto_ktp'] = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
        }
        if ($request->hasFile('foto_kk')) {
            $userData['foto_kk'] = $request->file('foto_kk')->store('users/foto_kk', 'public');
        }
        if ($request->hasFile('upload_latest_color_photo')) {
            $userData['upload_latest_color_photo'] = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
        }

        $user = User::create($userData);

        event(new Registered($user));

        // Don't auto-login, let user login manually
        // Auth::login($user);

        return redirect()->route('login')->with('status', 'Pendaftaran berhasil! Silakan login dengan email dan password Anda.');
    }

    private function generateNIK()
    {
        // Generate NIK with format: YYYYMMDD + 4 random digits
        $date = now()->format('Ymd');
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return $date . $random;
    }
}
