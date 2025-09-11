<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        \Log::info('REQUEST ALL:', $request->all());
        \Log::info('VALIDATED:', $request->validated());
        $user = $request->user();
        $data = $request->validated();

        // Update all fields that are provided (excluding readonly work fields)
        $allowedFields = [
            'nama_lengkap', 'nama_panggilan', 'email', 'no_hp', 'jenis_kelamin',
            'tempat_lahir', 'tanggal_lahir', 'suku', 'agama', 'status_pernikahan',
            'golongan_darah', 'alamat', 'alamat_ktp', 'pin_pos', 'nama_rekening', 'no_rekening',
            'npwp_number', 'bpjs_health_number', 'bpjs_employment_number',
            'last_education', 'name_school_college', 'school_college_major',
            'nama_kontak_darurat', 'no_hp_kontak_darurat', 'hubungan_kontak_darurat',
            'no_ktp', 'nomor_kk', 'imei'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $user->$field = $data[$field];
            }
        }

        // Handle file uploads
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
        }
        if ($request->hasFile('foto_ktp')) {
            if ($user->foto_ktp) {
                Storage::disk('public')->delete($user->foto_ktp);
            }
            $user->foto_ktp = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
        }
        if ($request->hasFile('foto_kk')) {
            if ($user->foto_kk) {
                Storage::disk('public')->delete($user->foto_kk);
            }
            $user->foto_kk = $request->file('foto_kk')->store('users/foto_kk', 'public');
        }
        if ($request->hasFile('upload_latest_color_photo')) {
            if ($user->upload_latest_color_photo) {
                Storage::disk('public')->delete($user->upload_latest_color_photo);
            }
            $user->upload_latest_color_photo = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
