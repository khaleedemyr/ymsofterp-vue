<script setup>
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';


const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'success']);

const activeTab = ref('personal');

// Options for dropdowns
const jenisKelaminOptions = [
    { value: 'L', label: 'Laki-laki' },
    { value: 'P', label: 'Perempuan' },
];
const statusPernikahanOptions = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Menikah' },
    { value: 'divorced', label: 'Cerai' },
];
const agamaOptions = [
    { value: 'Islam', label: 'Islam' },
    { value: 'Kristen', label: 'Kristen' },
    { value: 'Katolik', label: 'Katolik' },
    { value: 'Hindu', label: 'Hindu' },
    { value: 'Buddha', label: 'Buddha' },
    { value: 'Konghucu', label: 'Konghucu' },
];
const golonganDarahOptions = [
    { value: 'A', label: 'A' },
    { value: 'B', label: 'B' },
    { value: 'AB', label: 'AB' },
    { value: 'O', label: 'O' },
];
const hubunganKontakOptions = [
    { value: 'Ayah', label: 'Ayah' },
    { value: 'Ibu', label: 'Ibu' },
    { value: 'Suami', label: 'Suami' },
    { value: 'Istri', label: 'Istri' },
    { value: 'Anak', label: 'Anak' },
    { value: 'Saudara', label: 'Saudara' },
    { value: 'Lainnya', label: 'Lainnya' },
];
const pendidikanOptions = [
    { value: 'SD', label: 'SD' },
    { value: 'SMP', label: 'SMP' },
    { value: 'SMA', label: 'SMA' },
    { value: 'D3', label: 'D3' },
    { value: 'S1', label: 'S1' },
    { value: 'S2', label: 'S2' },
    { value: 'S3', label: 'S3' },
];

const form = useForm({
    // Personal Info
    nama_lengkap: '',
    nama_panggilan: '',
    email: '',
    password: '',
    password_confirmation: '',
    no_hp: '',
    jenis_kelamin: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    suku: '',
    agama: '',
    status_pernikahan: '',
    golongan_darah: '',
    
    // Address
    alamat: '',
    alamat_ktp: '',
    
    // Work Info (without jabatan, outlet, divisi)
    pin_pos: '',
    pin_payroll: '',
    
    // Financial
    nama_rekening: '',
    no_rekening: '',
    npwp_number: '',
    bpjs_health_number: '',
    bpjs_employment_number: '',
    
    // Education
    last_education: '',
    name_school_college: '',
    school_college_major: '',
    
    // Emergency Contact
    nama_kontak_darurat: '',
    no_hp_kontak_darurat: '',
    hubungan_kontak_darurat: '',
    
    // Documents
    no_ktp: '',
    nomor_kk: '',
    
    // Files
    avatar: null,
    foto_ktp: null,
    foto_kk: null,
    upload_latest_color_photo: null,
});

const previewUrl = ref(null);
const isLoading = ref(false);
const alertMessage = ref('');
const alertType = ref(''); // 'success', 'error', 'warning'
const showAlert = ref(false);

// Handle file uploads
function handleFileChange(e, field) {
    const file = e.target.files[0];
    if (file) {
        form[field] = file;
        if (field === 'avatar') {
            previewUrl.value = URL.createObjectURL(file);
        }
    }
}

// Show alert in modal
function showModalAlert(message, type = 'error') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        showAlert.value = false;
    }, 5000);
}

// Hide alert
function hideAlert() {
    showAlert.value = false;
}

watch(() => props.show, (val) => {
    if (val) {
        activeTab.value = 'personal';
        // Reset form when modal opens
        form.reset();
        previewUrl.value = null;
    }
});

const submitRegister = () => {
    // Validasi field yang wajib diisi
    const requiredFields = {
        // Personal Info
        'nama_lengkap': 'Nama Lengkap',
        'nama_panggilan': 'Nama Panggilan',
        'email': 'Email',
        'password': 'Password',
        'password_confirmation': 'Konfirmasi Password',
        'no_hp': 'No HP',
        'jenis_kelamin': 'Jenis Kelamin',
        'tempat_lahir': 'Tempat Lahir',
        'tanggal_lahir': 'Tanggal Lahir',
        'suku': 'Suku',
        'agama': 'Agama',
        'status_pernikahan': 'Status Pernikahan',
        'golongan_darah': 'Golongan Darah',
        
        // Contact Info
        'alamat': 'Alamat',
        'alamat_ktp': 'Alamat KTP',
        'pin_payroll': 'PIN Payroll',
        'nama_kontak_darurat': 'Nama Kontak Darurat',
        'no_hp_kontak_darurat': 'No HP Kontak Darurat',
        'hubungan_kontak_darurat': 'Hubungan Kontak Darurat',
        
        // Documents Info
        'no_ktp': 'No KTP',
        'nomor_kk': 'Nomor KK',
        'npwp_number': 'NPWP Number',
        'bpjs_health_number': 'BPJS Health Number',
        'bpjs_employment_number': 'BPJS Employment Number',
        'last_education': 'Pendidikan Terakhir',
        'name_school_college': 'Nama Sekolah/Kampus',
        'school_college_major': 'Jurusan',
        'nama_rekening': 'Nama Rekening',
        'no_rekening': 'No Rekening',
        'foto_ktp': 'Foto KTP',
        'foto_kk': 'Foto KK',
        'upload_latest_color_photo': 'Upload Latest Color Photo'
    };

    const emptyFields = [];
    
    // Cek field yang kosong
    Object.entries(requiredFields).forEach(([key, label]) => {
        const value = form[key];
        if (!value || (typeof value === 'string' && value.trim() === '') || value === null || value === undefined) {
            emptyFields.push(label);
        }
    });

    // Jika ada field yang kosong, tampilkan alert di modal
    if (emptyFields.length > 0) {
        const fieldList = emptyFields.map(field => `• ${field}`).join('\n');
        showModalAlert(`Data Belum Lengkap\n\nSilakan lengkapi data berikut:\n\n${fieldList}`, 'warning');
        return;
    }

    // Validasi password confirmation
    if (form.password !== form.password_confirmation) {
        showModalAlert('Password Tidak Cocok\n\nPassword dan Konfirmasi Password tidak sama. Silakan periksa kembali.', 'error');
        return;
    }

    isLoading.value = true;
    
    console.log('Form data before sending:', form.data());
    console.log('no_ktp value:', form.no_ktp);
    
    const fd = new FormData();
    Object.entries(form.data()).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
            fd.append(key, value);
            console.log(`Adding to FormData: ${key} = ${value}`);
        }
    });
    
    console.log('FormData contents:');
    for (let [key, value] of fd.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    axios.post('/register', fd, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    }).then(() => {
        showModalAlert('Pendaftaran berhasil! Silakan login dengan email dan password Anda.', 'success');
        
        // Close modal after 2 seconds
        setTimeout(() => {
            emit('success');
            emit('close');
            if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
                URL.revokeObjectURL(previewUrl.value);
            }
        }, 2000);
    }).catch((error) => {
        console.error('Register error:', error);
        if (error.response?.data?.errors) {
            // Handle validation errors
            const errors = error.response.data.errors;
            
            // Field display names mapping
            const getFieldDisplayName = (field) => {
                const fieldNames = {
                    'nama_lengkap': 'Nama Lengkap',
                    'nama_panggilan': 'Nama Panggilan',
                    'email': 'Email',
                    'password': 'Password',
                    'password_confirmation': 'Konfirmasi Password',
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
                    'pin_pos': 'PIN POS',
                    'pin_payroll': 'PIN Payroll',
                    'nama_kontak_darurat': 'Nama Kontak Darurat',
                    'no_hp_kontak_darurat': 'No HP Kontak Darurat',
                    'hubungan_kontak_darurat': 'Hubungan Kontak Darurat',
                    'no_ktp': 'No KTP',
                    'nomor_kk': 'Nomor KK',
                    'npwp_number': 'NPWP',
                    'bpjs_health_number': 'BPJS Kesehatan',
                    'bpjs_employment_number': 'BPJS Ketenagakerjaan',
                    'last_education': 'Pendidikan Terakhir',
                    'name_school_college': 'Nama Sekolah/Kampus',
                    'school_college_major': 'Jurusan',
                    'nama_rekening': 'Nama Rekening',
                    'no_rekening': 'No Rekening',
                    'avatar': 'Avatar',
                    'foto_ktp': 'Foto KTP',
                    'foto_kk': 'Foto KK',
                    'upload_latest_color_photo': 'Foto Terbaru'
                };
                return fieldNames[field] || field;
            };
            
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
            
            // Create plain text version for modal alert
            let plainErrorMessage = 'Gagal mendaftar akun. Periksa field berikut:\n\n';
            Object.keys(errors).forEach(field => {
                const fieldName = getFieldDisplayName(field);
                if (Array.isArray(errors[field])) {
                    errors[field].forEach(error => {
                        plainErrorMessage += `• ${fieldName}: ${error}\n`;
                    });
                } else {
                    plainErrorMessage += `• ${fieldName}: ${errors[field]}\n`;
                }
            });
            plainErrorMessage += '\nSilakan perbaiki field yang bermasalah dan coba lagi.';
            
            showModalAlert(plainErrorMessage, 'error');
            
            // Set form errors for individual field display
            form.setError(errors);
        } else {
            showModalAlert('Gagal mendaftar! Silakan coba lagi.', 'error');
        }
    }).finally(() => {
        isLoading.value = false;
    });
};
</script>

<style scoped>
/* Ensure SweetAlert appears above modal */
:deep(.swal2-container) {
    z-index: 999999 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:deep(.swal2-popup) {
    z-index: 999999 !important;
    position: relative !important;
}

:deep(.swal2-backdrop-show) {
    z-index: 999998 !important;
}

:deep(.swal2-backdrop) {
    z-index: 999998 !important;
}

:deep(.swal2-html-container) {
    z-index: 999999 !important;
}

:deep(.swal2-actions) {
    z-index: 999999 !important;
}

:deep(.swal2-title) {
    z-index: 999999 !important;
}

:deep(.swal2-content) {
    z-index: 999999 !important;
}
</style>

<style>
/* Global SweetAlert z-index override */
.swal2-container {
    z-index: 999999 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.swal2-popup {
    z-index: 999999 !important;
    position: relative !important;
}

.swal2-backdrop {
    z-index: 999998 !important;
}

.swal2-backdrop-show {
    z-index: 999998 !important;
}

.swal2-html-container {
    z-index: 999999 !important;
}

.swal2-actions {
    z-index: 999999 !important;
}

.swal2-title {
    z-index: 999999 !important;
}

.swal2-content {
    z-index: 999999 !important;
}
</style>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6 min-w-[600px] max-w-4xl max-h-[90vh] overflow-y-auto">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Daftar Akun Baru
            </h2>
            
            <!-- Alert Message -->
            <div v-if="showAlert" :class="[
                'mb-4 p-4 rounded-lg border-l-4',
                alertType === 'success' ? 'bg-green-50 border-green-400 text-green-700' :
                alertType === 'warning' ? 'bg-yellow-50 border-yellow-400 text-yellow-700' :
                'bg-red-50 border-red-400 text-red-700'
            ]">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i :class="[
                            'text-lg',
                            alertType === 'success' ? 'fa-solid fa-check-circle' :
                            alertType === 'warning' ? 'fa-solid fa-exclamation-triangle' :
                            'fa-solid fa-times-circle'
                        ]"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium whitespace-pre-line">{{ alertMessage }}</p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <button @click="hideAlert" class="text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="flex border-b mb-4 overflow-x-auto">
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'personal' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'personal'">Personal</button>
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'contact' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'contact'">Contact</button>
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'documents' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'documents'">Documents</button>
            </div>

            <!-- Personal Tab -->
            <div v-if="activeTab === 'personal'">
                <form @submit.prevent="submitRegister" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Avatar Upload -->
                        <div class="md:col-span-2">
                            <InputLabel for="avatar" value="Profile Picture" />
                            <div class="mt-2 flex items-center gap-4">
                                <div class="relative">
                                    <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-gray-200">
                                        <img 
                                            v-if="previewUrl" 
                                            :src="previewUrl" 
                                            alt="Avatar preview" 
                                            class="w-full h-full object-cover"
                                        />
                                        <div 
                                            v-else 
                                            class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400"
                                        >
                                            <i class="fas fa-user text-2xl"></i>
                                        </div>
                                    </div>
                                    <label 
                                        for="avatar-upload" 
                                        class="absolute bottom-0 right-0 bg-blue-500 text-white rounded-full p-1 cursor-pointer hover:bg-blue-600"
                                    >
                                        <i class="fas fa-camera"></i>
                                    </label>
                                    <input 
                                        id="avatar-upload" 
                                        type="file" 
                                        class="hidden" 
                                        accept="image/*"
                                        @change="handleFileChange($event, 'avatar')"
                                    />
                                </div>
                                <div class="text-sm text-gray-500">
                                    Click the camera icon to upload your profile picture
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.avatar" />
                        </div>

                        <div>
                            <InputLabel for="nama_lengkap" value="Nama Lengkap *" />
                            <TextInput
                                id="nama_lengkap"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_lengkap"
                                required
                                autofocus
                                autocomplete="name"
                            />
                            <InputError class="mt-2" :message="form.errors.nama_lengkap" />
                        </div>

                        <div>
                            <InputLabel for="nama_panggilan" value="Nama Panggilan *" />
                            <TextInput
                                id="nama_panggilan"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_panggilan"
                                required
                                autocomplete="nickname"
                            />
                            <InputError class="mt-2" :message="form.errors.nama_panggilan" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email *" />
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

                        <div>
                            <InputLabel for="password" value="Password *" />
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

                        <div>
                            <InputLabel for="password_confirmation" value="Konfirmasi Password *" />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                v-model="form.password_confirmation"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <div>
                            <InputLabel for="no_hp" value="No HP *" />
                            <TextInput
                                id="no_hp"
                                type="tel"
                                class="mt-1 block w-full"
                                v-model="form.no_hp"
                                required
                                autocomplete="tel"
                            />
                            <InputError class="mt-2" :message="form.errors.no_hp" />
                        </div>

                        <div>
                            <InputLabel for="jenis_kelamin" value="Jenis Kelamin *" />
                            <select v-model="form.jenis_kelamin" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Jenis Kelamin</option>
                                <option v-for="opt in jenisKelaminOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.jenis_kelamin" />
                        </div>

                        <div>
                            <InputLabel for="tempat_lahir" value="Tempat Lahir *" />
                            <TextInput
                                id="tempat_lahir"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.tempat_lahir"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.tempat_lahir" />
                        </div>

                        <div>
                            <InputLabel for="tanggal_lahir" value="Tanggal Lahir *" />
                            <TextInput
                                id="tanggal_lahir"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="form.tanggal_lahir"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.tanggal_lahir" />
                        </div>

                        <div>
                            <InputLabel for="suku" value="Suku *" />
                            <TextInput
                                id="suku"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.suku"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.suku" />
                        </div>

                        <div>
                            <InputLabel for="agama" value="Agama *" />
                            <select v-model="form.agama" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Agama</option>
                                <option v-for="opt in agamaOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.agama" />
                        </div>

                        <div>
                            <InputLabel for="status_pernikahan" value="Status Pernikahan *" />
                            <select v-model="form.status_pernikahan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Status</option>
                                <option v-for="opt in statusPernikahanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.status_pernikahan" />
                        </div>

                        <div>
                            <InputLabel for="golongan_darah" value="Golongan Darah *" />
                            <select v-model="form.golongan_darah" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Golongan Darah</option>
                                <option v-for="opt in golonganDarahOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.golongan_darah" />
                        </div>

                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="isLoading">
                            <span v-if="isLoading">Mendaftar...</span>
                            <span v-else>DAFTAR</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Contact Tab -->
            <div v-else-if="activeTab === 'contact'">
                <form @submit.prevent="submitRegister" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Alamat</h3>
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="alamat" value="Alamat *" />
                            <textarea v-model="form.alamat" required rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            <InputError class="mt-2" :message="form.errors.alamat" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="alamat_ktp" value="Alamat KTP *" />
                            <textarea v-model="form.alamat_ktp" required rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            <InputError class="mt-2" :message="form.errors.alamat_ktp" />
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Informasi Kerja</h3>
                        </div>

                        <div>
                            <InputLabel for="pin_pos" value="PIN POS" />
                            <TextInput
                                id="pin_pos"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.pin_pos"
                                maxlength="10"
                            />
                            <InputError class="mt-2" :message="form.errors.pin_pos" />
                        </div>

                        <div>
                            <InputLabel for="pin_payroll" value="PIN Payroll *" />
                            <TextInput
                                id="pin_payroll"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.pin_payroll"
                                maxlength="10"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.pin_payroll" />
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Kontak Darurat</h3>
                        </div>

                        <div>
                            <InputLabel for="nama_kontak_darurat" value="Nama Kontak Darurat *" />
                            <TextInput
                                id="nama_kontak_darurat"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_kontak_darurat"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.nama_kontak_darurat" />
                        </div>

                        <div>
                            <InputLabel for="no_hp_kontak_darurat" value="No HP Kontak Darurat *" />
                            <TextInput
                                id="no_hp_kontak_darurat"
                                type="tel"
                                class="mt-1 block w-full"
                                v-model="form.no_hp_kontak_darurat"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.no_hp_kontak_darurat" />
                        </div>

                        <div>
                            <InputLabel for="hubungan_kontak_darurat" value="Hubungan Kontak Darurat *" />
                            <select v-model="form.hubungan_kontak_darurat" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Hubungan</option>
                                <option v-for="opt in hubunganKontakOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.hubungan_kontak_darurat" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="isLoading">
                            <span v-if="isLoading">Mendaftar...</span>
                            <span v-else>DAFTAR</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Documents Tab -->
            <div v-else-if="activeTab === 'documents'">
                <form @submit.prevent="submitRegister" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="no_ktp" value="No KTP *" />
                            <TextInput
                                id="no_ktp"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.no_ktp"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.no_ktp" />
                        </div>

                        <div>
                            <InputLabel for="nomor_kk" value="Nomor KK *" />
                            <TextInput
                                id="nomor_kk"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nomor_kk"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.nomor_kk" />
                        </div>

                        <div>
                            <InputLabel for="npwp_number" value="NPWP Number *" />
                            <TextInput
                                id="npwp_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.npwp_number"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.npwp_number" />
                        </div>

                        <div>
                            <InputLabel for="bpjs_health_number" value="BPJS Health Number *" />
                            <TextInput
                                id="bpjs_health_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.bpjs_health_number"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.bpjs_health_number" />
                        </div>

                        <div>
                            <InputLabel for="bpjs_employment_number" value="BPJS Employment Number *" />
                            <TextInput
                                id="bpjs_employment_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.bpjs_employment_number"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.bpjs_employment_number" />
                        </div>

                        <div>
                            <InputLabel for="last_education" value="Pendidikan Terakhir *" />
                            <select v-model="form.last_education" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Pendidikan</option>
                                <option v-for="opt in pendidikanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.last_education" />
                        </div>

                        <div>
                            <InputLabel for="name_school_college" value="Nama Sekolah/Kampus *" />
                            <TextInput
                                id="name_school_college"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name_school_college"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name_school_college" />
                        </div>

                        <div>
                            <InputLabel for="school_college_major" value="Jurusan *" />
                            <TextInput
                                id="school_college_major"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.school_college_major"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.school_college_major" />
                        </div>

                        <div>
                            <InputLabel for="nama_rekening" value="Nama Rekening *" />
                            <TextInput
                                id="nama_rekening"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_rekening"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.nama_rekening" />
                        </div>

                        <div>
                            <InputLabel for="no_rekening" value="No Rekening *" />
                            <TextInput
                                id="no_rekening"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.no_rekening"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.no_rekening" />
                        </div>

                        <!-- File Uploads -->
                        <div>
                            <InputLabel for="foto_ktp" value="Foto KTP *" />
                            <input type="file" required @change="handleFileChange($event, 'foto_ktp')" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
                            <InputError class="mt-2" :message="form.errors.foto_ktp" />
                        </div>

                        <div>
                            <InputLabel for="foto_kk" value="Foto KK *" />
                            <input type="file" required @change="handleFileChange($event, 'foto_kk')" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
                            <InputError class="mt-2" :message="form.errors.foto_kk" />
                        </div>

                        <div>
                            <InputLabel for="upload_latest_color_photo" value="Upload Latest Color Photo *" />
                            <input type="file" required @change="handleFileChange($event, 'upload_latest_color_photo')" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
                            <InputError class="mt-2" :message="form.errors.upload_latest_color_photo" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="isLoading">
                            <span v-if="isLoading">Mendaftar...</span>
                            <span v-else>DAFTAR</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </Modal>
</template>
