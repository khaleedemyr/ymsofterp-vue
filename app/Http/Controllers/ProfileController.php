<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
    public function update(ProfileUpdateRequest $request)
    {
        \Log::info('REQUEST ALL:', $request->all());
        \Log::info('VALIDATED:', $request->validated());
        $user = $request->user();
        $data = $request->validated();

        // Update all fields that are provided (excluding readonly work fields)
        $allowedFields = [
            // Personal Info
            'nama_lengkap', 'nama_panggilan', 'email', 'no_hp', 'jenis_kelamin',
            'tempat_lahir', 'tanggal_lahir', 'suku', 'agama', 'status_pernikahan',
            'golongan_darah',
            
            // Address
            'alamat', 'alamat_ktp',
            
            // Work Info (only pin_pos and pin_payroll are editable, others are readonly)
            'pin_pos', 'pin_payroll',
            
            // Financial
            'nama_rekening', 'no_rekening', 'npwp_number', 'bpjs_health_number', 'bpjs_employment_number',
            
            // Education
            'last_education', 'name_school_college', 'school_college_major',
            
            // Emergency Contact
            'nama_kontak_darurat', 'no_hp_kontak_darurat', 'hubungan_kontak_darurat',
            
            // Documents
            'no_ktp', 'nomor_kk', 'imei'
        ];

        $updatedFields = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $user->$field = $data[$field];
                $updatedFields[] = $field;
                \Log::info("Updated field {$field}: {$data[$field]}");
            }
        }

        // File uploads are handled separately via updateAvatar method
        \Log::info('Updated fields:', $updatedFields);

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'updated_fields' => $updatedFields
        ]);
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(Request $request)
    {
        // Debug: Log semua data yang diterima
        \Log::info('Avatar update request data:', [
            'all' => $request->all(),
            'files' => $request->allFiles(),
            'hasFile_avatar' => $request->hasFile('avatar'),
            'file_avatar' => $request->file('avatar'),
            'input_avatar' => $request->input('avatar'),
        ]);
        
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
        $user->save();

        return response()->json([
            'message' => 'Avatar updated successfully',
            'avatar_url' => Storage::disk('public')->url($user->avatar)
        ]);
    }

    /**
     * Update the user's documents (foto_ktp, foto_kk, upload_latest_color_photo).
     */
    public function updateDocuments(Request $request)
    {
        \Log::info('=== UPDATE DOCUMENTS REQUEST ===');
        \Log::info('Request all:', $request->all());
        \Log::info('Request files:', $request->allFiles());
        \Log::info('Has foto_ktp: ' . ($request->hasFile('foto_ktp') ? 'true' : 'false'));
        \Log::info('Has foto_kk: ' . ($request->hasFile('foto_kk') ? 'true' : 'false'));
        \Log::info('Has upload_latest_color_photo: ' . ($request->hasFile('upload_latest_color_photo') ? 'true' : 'false'));
        \Log::info('Existing foto_ktp: ' . ($request->input('existing_foto_ktp') ?? 'null'));
        \Log::info('Existing foto_kk: ' . ($request->input('existing_foto_kk') ?? 'null'));
        \Log::info('Existing upload_latest_color_photo: ' . ($request->input('existing_upload_latest_color_photo') ?? 'null'));
        
        $request->validate([
            'foto_ktp' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'foto_kk' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'upload_latest_color_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        $user = $request->user();

        // Handle foto_ktp upload
        if ($request->hasFile('foto_ktp') && $request->file('foto_ktp')->isValid()) {
            \Log::info('Uploading NEW foto_ktp...');
            if ($user->foto_ktp) {
                Storage::disk('public')->delete($user->foto_ktp);
            }
            $user->foto_ktp = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
            \Log::info('foto_ktp uploaded to: ' . $user->foto_ktp);
        } elseif ($request->input('existing_foto_ktp')) {
            \Log::info('Preserving EXISTING foto_ktp: ' . $request->input('existing_foto_ktp'));
            $user->foto_ktp = $request->input('existing_foto_ktp');
        }

        // Handle foto_kk upload
        if ($request->hasFile('foto_kk') && $request->file('foto_kk')->isValid()) {
            \Log::info('Uploading NEW foto_kk...');
            if ($user->foto_kk) {
                Storage::disk('public')->delete($user->foto_kk);
            }
            $user->foto_kk = $request->file('foto_kk')->store('users/foto_kk', 'public');
            \Log::info('foto_kk uploaded to: ' . $user->foto_kk);
        } elseif ($request->input('existing_foto_kk')) {
            \Log::info('Preserving EXISTING foto_kk: ' . $request->input('existing_foto_kk'));
            $user->foto_kk = $request->input('existing_foto_kk');
        }

        // Handle upload_latest_color_photo upload
        if ($request->hasFile('upload_latest_color_photo') && $request->file('upload_latest_color_photo')->isValid()) {
            \Log::info('Uploading NEW upload_latest_color_photo...');
            if ($user->upload_latest_color_photo) {
                Storage::disk('public')->delete($user->upload_latest_color_photo);
            }
            $user->upload_latest_color_photo = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
            \Log::info('upload_latest_color_photo uploaded to: ' . $user->upload_latest_color_photo);
        } elseif ($request->input('existing_upload_latest_color_photo')) {
            \Log::info('Preserving EXISTING upload_latest_color_photo: ' . $request->input('existing_upload_latest_color_photo'));
            $user->upload_latest_color_photo = $request->input('existing_upload_latest_color_photo');
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Documents updated successfully'
        ]);
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
