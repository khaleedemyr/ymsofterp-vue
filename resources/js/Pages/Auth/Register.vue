<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// Field display names mapping
const getFieldDisplayName = (field) => {
    const fieldNames = {
        'name': 'Nama Lengkap',
        'email': 'Email',
        'password': 'Password',
        'password_confirmation': 'Konfirmasi Password',
        'nama_lengkap': 'Nama Lengkap',
        'nama_panggilan': 'Nama Panggilan',
        'no_hp': 'No HP',
        'jenis_kelamin': 'Jenis Kelamin',
        'tempat_lahir': 'Tempat Lahir',
        'tanggal_lahir': 'Tanggal Lahir',
        'suku': 'Suku',
        'agama': 'Agama',
        'status_pernikahan': 'Status Pernikahan',
        'golongan_darah': 'Golongan Darah',
        'alamat': 'Alamat',
        'alamat_ktp': 'Alamat KTP',
        'nama_rekening': 'Nama Rekening',
        'no_rekening': 'No Rekening',
        'npwp_number': 'NPWP',
        'bpjs_health_number': 'BPJS Kesehatan',
        'bpjs_employment_number': 'BPJS Ketenagakerjaan',
        'last_education': 'Pendidikan Terakhir',
        'name_school_college': 'Nama Sekolah/Kampus',
        'school_college_major': 'Jurusan',
        'nama_kontak_darurat': 'Nama Kontak Darurat',
        'no_hp_kontak_darurat': 'No HP Kontak Darurat',
        'hubungan_kontak_darurat': 'Hubungan Kontak Darurat',
        'no_ktp': 'No KTP',
        'nomor_kk': 'Nomor KK',
        'avatar': 'Avatar',
        'foto_ktp': 'Foto KTP',
        'foto_kk': 'Foto KK',
        'upload_latest_color_photo': 'Foto Terbaru'
    };
    return fieldNames[field] || field;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onError: (errors) => {
            console.error('Registration failed with errors:', errors);
            
            // Create detailed error message for SweetAlert
            let errorMessage = '<div class="text-left">';
            errorMessage += '<p class="font-semibold text-red-600 mb-3">Gagal mendaftar akun. Periksa field berikut:</p>';
            errorMessage += '<ul class="list-disc list-inside text-sm space-y-1">';
            
            // Process validation errors
            if (typeof errors === 'object' && errors !== null) {
                Object.keys(errors).forEach(field => {
                    const fieldName = getFieldDisplayName(field);
                    if (Array.isArray(errors[field])) {
                        errors[field].forEach(error => {
                            errorMessage += `<li class="text-red-600"><strong>${fieldName}:</strong> ${error}</li>`;
                        });
                    } else {
                        errorMessage += `<li class="text-red-600"><strong>${fieldName}:</strong> ${errors[field]}</li>`;
                    }
                });
            } else {
                errorMessage += `<li class="text-red-600">${errors}</li>`;
            }
            
            errorMessage += '</ul>';
            errorMessage += '<p class="text-xs text-gray-500 mt-3">Silakan perbaiki field yang bermasalah dan coba lagi.</p>';
            errorMessage += '</div>';
            
            Swal.fire({
                icon: 'error',
                title: 'Gagal Mendaftar',
                html: errorMessage,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'OK',
                width: '500px'
            });
        }
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Already registered?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Register
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
