<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Personal Info
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'no_hp' => ['nullable', 'string', 'max:15'],
            'jenis_kelamin' => ['nullable', 'string', 'max:1'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'suku' => ['nullable', 'string', 'max:50'],
            'agama' => ['nullable', 'string', 'max:50'],
            'status_pernikahan' => ['nullable', 'string', 'max:50'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            
            // Address
            'alamat' => ['nullable', 'string'],
            'alamat_ktp' => ['nullable', 'string'],
            
            // Work Info (readonly fields removed - not allowed to be updated by user)
            'pin_pos' => ['nullable', 'string', 'max:10'],
            'pin_payroll' => ['required', 'string', 'max:10'],
            
            // Financial
            'nama_rekening' => ['nullable', 'string', 'max:255'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'npwp_number' => ['nullable', 'string', 'max:100'],
            'bpjs_health_number' => ['nullable', 'string', 'max:100'],
            'bpjs_employment_number' => ['nullable', 'string', 'max:100'],
            
            // Education
            'last_education' => ['nullable', 'string', 'max:100'],
            'name_school_college' => ['nullable', 'string', 'max:255'],
            'school_college_major' => ['nullable', 'string', 'max:255'],
            
            // Emergency Contact
            'nama_kontak_darurat' => ['nullable', 'string', 'max:255'],
            'no_hp_kontak_darurat' => ['nullable', 'string', 'max:15'],
            'hubungan_kontak_darurat' => ['nullable', 'string', 'max:50'],
            
            // Documents
            'no_ktp' => ['nullable', 'string', 'max:50'],
            'nomor_kk' => ['nullable', 'string', 'max:50'],
            'imei' => ['nullable', 'string', 'max:50'],
            
            // Files are handled separately - no file validation needed here
        ];
    }
}
