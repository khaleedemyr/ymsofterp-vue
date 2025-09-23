<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const activeTab = ref('personal');

// Dropdown data
const dropdownData = ref({ outlets: [], jabatans: [], divisions: [] });
const isLoadingDropdown = ref(false);

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
    
    // Work Info
    id_jabatan: '',
    id_outlet: '',
    division_id: '',
    tanggal_masuk: '',
    pin_pos: '',
    
    // Work Info Names (from API)
    jabatan_name: '',
    outlet_name: '',
    division_name: '',
    
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
    imei: '',
    
    // Files
    avatar: null,
    foto_ktp: null,
    foto_kk: null,
    upload_latest_color_photo: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const previewUrl = ref(null);
const isLoading = ref(false);

// Display names for readonly fields
const jabatanDisplayName = ref('');
const outletDisplayName = ref('');
const divisionDisplayName = ref('');
const tanggalMasukDisplay = ref('');

// Fetch dropdown data
async function fetchDropdownData() {
    isLoadingDropdown.value = true;
    try {
        const response = await axios.get(route('users.dropdown-data'));
        if (response.data.success) {
            dropdownData.value = {
                outlets: response.data.outlets || [],
                jabatans: response.data.jabatans || [],
                divisions: response.data.divisions || [],
            };
            
            // Update display names after dropdown data is loaded
            updateDisplayNames();
        }
    } catch (error) {
        console.error('Error fetching dropdown data:', error);
    } finally {
        isLoadingDropdown.value = false;
    }
}

// Fetch user data
const fetchUser = async () => {
    try {
        console.log('Fetching user data...');
        
        // Try API endpoint first
        try {
            const { data } = await axios.get('/api/auth/user');
            console.log('User data received from API:', data);
            
            // Populate all form fields
            Object.keys(form.data()).forEach(key => {
                if (data[key] !== undefined && data[key] !== null) {
                    form[key] = data[key];
                    console.log(`Setting ${key}:`, data[key]);
                }
            });
            
        previewUrl.value = data.avatar ? `/storage/${data.avatar}` : null;
        } catch (apiError) {
            console.error('API error:', apiError);
            
            // Fallback to test route
            try {
                const { data } = await axios.get('/test/user-data');
                console.log('User data received from test route:', data);
                
                if (data.success && data.user) {
                    Object.keys(form.data()).forEach(key => {
                        if (data.user[key] !== undefined && data.user[key] !== null) {
                            form[key] = data.user[key];
                            console.log(`Setting ${key} from test route:`, data.user[key]);
                        }
                    });
                    
                    previewUrl.value = data.user.avatar ? `/storage/${data.user.avatar}` : null;
                }
            } catch (testError) {
                console.error('Test route error:', testError);
            }
        }
        
        console.log('Form data after population:', form.data());
        
        // Update display names after data is loaded
        updateDisplayNames();
    } catch (e) {
        console.error('Error fetching user data:', e);
        console.error('Error response:', e.response?.data);
    }
};

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

// Helper functions for readonly fields
function getJabatanName() {
    console.log('getJabatanName - form.id_jabatan:', form.id_jabatan);
    console.log('getJabatanName - form.jabatan_name:', form.jabatan_name);
    console.log('getJabatanName - dropdownData.jabatans:', dropdownData.value.jabatans);
    
    // Try to get from API data first (jabatan_name)
    if (form.jabatan_name) {
        return form.jabatan_name;
    }
    
    // Fallback to dropdown data
    if (!form.id_jabatan) return 'Tidak ada jabatan';
    if (!dropdownData.value.jabatans || dropdownData.value.jabatans.length === 0) {
        return 'Loading...';
    }
    
    const jabatan = dropdownData.value.jabatans.find(j => j.id_jabatan == form.id_jabatan);
    console.log('getJabatanName - found jabatan:', jabatan);
    return jabatan ? jabatan.nama_jabatan : 'Jabatan tidak ditemukan';
}

function getOutletName() {
    console.log('getOutletName - form.id_outlet:', form.id_outlet);
    console.log('getOutletName - form.outlet_name:', form.outlet_name);
    console.log('getOutletName - dropdownData.outlets:', dropdownData.value.outlets);
    
    // Try to get from API data first (outlet_name)
    if (form.outlet_name) {
        return form.outlet_name;
    }
    
    // Fallback to dropdown data
    if (!form.id_outlet) return 'Tidak ada outlet';
    if (!dropdownData.value.outlets || dropdownData.value.outlets.length === 0) {
        return 'Loading...';
    }
    
    const outlet = dropdownData.value.outlets.find(o => o.id_outlet == form.id_outlet);
    console.log('getOutletName - found outlet:', outlet);
    return outlet ? outlet.nama_outlet : 'Outlet tidak ditemukan';
}

function getDivisionName() {
    console.log('getDivisionName - form.division_id:', form.division_id);
    console.log('getDivisionName - form.division_name:', form.division_name);
    console.log('getDivisionName - dropdownData.divisions:', dropdownData.value.divisions);
    
    // Try to get from API data first (division_name)
    if (form.division_name) {
        return form.division_name;
    }
    
    // Fallback to dropdown data
    if (!form.division_id) return 'Tidak ada divisi';
    if (!dropdownData.value.divisions || dropdownData.value.divisions.length === 0) {
        return 'Loading...';
    }
    
    const division = dropdownData.value.divisions.find(d => d.id_division == form.division_id);
    console.log('getDivisionName - found division:', division);
    return division ? division.nama_division : 'Divisi tidak ditemukan';
}

function formatDate(dateString) {
    console.log('formatDate - dateString:', dateString);
    if (!dateString) return 'Tidak ada tanggal';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Tanggal tidak valid';
        
        return date.toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (error) {
        console.error('formatDate error:', error);
        return 'Format tanggal salah';
    }
}

// Update display names
function updateDisplayNames() {
    jabatanDisplayName.value = getJabatanName();
    outletDisplayName.value = getOutletName();
    divisionDisplayName.value = getDivisionName();
    tanggalMasukDisplay.value = formatDate(form.tanggal_masuk);
}

watch(() => props.show, (val) => {
    if (val) {
        activeTab.value = 'personal';
        
        // First populate from usePage data as fallback
        const user = usePage().props.auth.user;
        if (user) {
            console.log('User from usePage:', user);
            Object.keys(form.data()).forEach(key => {
                if (user[key] !== undefined && user[key] !== null) {
                    form[key] = user[key];
                }
            });
        previewUrl.value = user.avatar ? `/storage/${user.avatar}` : null;
        }
        
        // Then fetch fresh data from API
        fetchUser();
        fetchDropdownData();
        
        // Update display names after a short delay to ensure data is loaded
        setTimeout(() => {
            updateDisplayNames();
        }, 100);
    }
});

const submitProfile = async () => {
    isLoading.value = true;
    
    try {
        // 1. Update avatar separately if it exists
        if (form.avatar && form.avatar instanceof File) {
            await updateAvatar();
        }
        
        // 2. Update documents separately if they exist
        if (hasDocumentFiles()) {
            await updateDocuments();
        }
        
        // 3. Update other profile data (excluding all file fields)
        await updateProfileData();
        
        // 4. Reload user data and show success
        Inertia.reload({ only: ['auth'] });
        Swal.fire('Success', 'Profile updated successfully!', 'success');
        emit('close');
        
        if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl.value);
        }
    } catch (error) {
        console.error('Profile update error:', error);
        console.error('Error response:', error.response?.data);
        Swal.fire('Error', 'Failed to update profile!', 'error');
    } finally {
        isLoading.value = false;
    }
};

const hasDocumentFiles = () => {
    return (form.foto_ktp && form.foto_ktp instanceof File) ||
           (form.foto_kk && form.foto_kk instanceof File) ||
           (form.upload_latest_color_photo && form.upload_latest_color_photo instanceof File);
};

const updateAvatar = async () => {
    const fd = new FormData();
    fd.append('avatar', form.avatar);
    
    await axios.patch(route('profile.update-avatar'), fd, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
        _method: 'patch',
    });
};

const updateDocuments = async () => {
    const fd = new FormData();
    
    if (form.foto_ktp && form.foto_ktp instanceof File) {
        fd.append('foto_ktp', form.foto_ktp);
    }
    if (form.foto_kk && form.foto_kk instanceof File) {
        fd.append('foto_kk', form.foto_kk);
    }
    if (form.upload_latest_color_photo && form.upload_latest_color_photo instanceof File) {
        fd.append('upload_latest_color_photo', form.upload_latest_color_photo);
    }
    
    await axios.post(route('profile.update-documents'), fd, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
        _method: 'patch',
    });
};

const updateProfileData = async () => {
    const fd = new FormData();
    
    // Fields that should NOT be submitted (readonly work fields)
    const readonlyFields = ['id_jabatan', 'id_outlet', 'division_id', 'tanggal_masuk'];
    
    // File fields that should be excluded from profile update
    const fileFields = ['avatar', 'foto_ktp', 'foto_kk', 'upload_latest_color_photo'];
    
    Object.entries(form.data()).forEach(([key, value]) => {
        // Skip readonly fields
        if (readonlyFields.includes(key)) {
            return;
        }
        
        // Skip file fields (they are handled separately)
        if (fileFields.includes(key)) {
            return;
        }
        
        // For other fields, only send if they have meaningful values
        if (value !== null && value !== undefined && value !== '') {
            fd.append(key, value);
        }
    });
    
    console.log('Profile data FormData contents:');
    for (let [key, value] of fd.entries()) {
        console.log(key, value);
    }
    
    await axios.post(route('profile.update'), fd, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
        _method: 'patch',
    });
};

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 transition-all">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden animate-fade-in">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-700 px-6 py-4 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-user text-2xl"></i>
                        <h3 class="text-xl font-bold">Update Profile</h3>
                    </div>
                    <button @click="emit('close')" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6 min-w-[600px] max-h-[calc(90vh-140px)] overflow-y-auto">
            <div class="flex border-b mb-4 overflow-x-auto">
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'personal' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'personal'">Personal</button>
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'work' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'work'">Work</button>
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'contact' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'contact'">Contact</button>
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'documents' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'documents'">Documents</button>
                <button :class="['px-4 py-2 -mb-px font-semibold whitespace-nowrap', activeTab === 'password' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'password'">Password</button>
            </div>
            <!-- Personal Tab -->
            <div v-if="activeTab === 'personal'">
                <form @submit.prevent="submitProfile" class="space-y-6">
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
                                Click the camera icon to change your profile picture
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
                            <InputLabel for="nama_panggilan" value="Nama Panggilan" />
                            <TextInput
                                id="nama_panggilan"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_panggilan"
                                autocomplete="nickname"
                            />
                            <InputError class="mt-2" :message="form.errors.nama_panggilan" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                        <div>
                            <InputLabel for="no_hp" value="No HP" />
                            <TextInput
                                id="no_hp"
                                type="tel"
                                class="mt-1 block w-full"
                                v-model="form.no_hp"
                                autocomplete="tel"
                            />
                            <InputError class="mt-2" :message="form.errors.no_hp" />
                        </div>

                        <div>
                            <InputLabel for="jenis_kelamin" value="Jenis Kelamin" />
                            <select v-model="form.jenis_kelamin" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Jenis Kelamin</option>
                                <option v-for="opt in jenisKelaminOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.jenis_kelamin" />
                        </div>

                        <div>
                            <InputLabel for="tempat_lahir" value="Tempat Lahir" />
                            <TextInput
                                id="tempat_lahir"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.tempat_lahir"
                            />
                            <InputError class="mt-2" :message="form.errors.tempat_lahir" />
                        </div>

                        <div>
                            <InputLabel for="tanggal_lahir" value="Tanggal Lahir" />
                            <TextInput
                                id="tanggal_lahir"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="form.tanggal_lahir"
                            />
                            <InputError class="mt-2" :message="form.errors.tanggal_lahir" />
                        </div>

                        <div>
                            <InputLabel for="suku" value="Suku" />
                            <TextInput
                                id="suku"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.suku"
                            />
                            <InputError class="mt-2" :message="form.errors.suku" />
                        </div>

                        <div>
                            <InputLabel for="agama" value="Agama" />
                            <select v-model="form.agama" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Agama</option>
                                <option v-for="opt in agamaOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.agama" />
                        </div>

                        <div>
                            <InputLabel for="status_pernikahan" value="Status Pernikahan" />
                            <select v-model="form.status_pernikahan" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Status</option>
                                <option v-for="opt in statusPernikahanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.status_pernikahan" />
                        </div>

                        <div>
                            <InputLabel for="golongan_darah" value="Golongan Darah" />
                            <select v-model="form.golongan_darah" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
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
                            <span v-if="isLoading">Saving...</span>
                            <span v-else>SAVE CHANGES</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Work Tab -->
            <div v-else-if="activeTab === 'work'">
                <form @submit.prevent="submitProfile" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="id_jabatan" value="Jabatan" />
                            <TextInput
                                id="id_jabatan"
                                type="text"
                                class="mt-1 block w-full bg-gray-100"
                                v-model="jabatanDisplayName"
                                readonly
                            />
                            <p class="text-xs text-gray-500 mt-1">Jabatan tidak dapat diubah</p>
                        </div>

                        <div>
                            <InputLabel for="id_outlet" value="Outlet" />
                            <TextInput
                                id="id_outlet"
                                type="text"
                                class="mt-1 block w-full bg-gray-100"
                                v-model="outletDisplayName"
                                readonly
                            />
                            <p class="text-xs text-gray-500 mt-1">Outlet tidak dapat diubah</p>
                        </div>

                        <div>
                            <InputLabel for="division_id" value="Divisi" />
                            <TextInput
                                id="division_id"
                                type="text"
                                class="mt-1 block w-full bg-gray-100"
                                v-model="divisionDisplayName"
                                readonly
                            />
                            <p class="text-xs text-gray-500 mt-1">Divisi tidak dapat diubah</p>
                        </div>

                        <div>
                            <InputLabel for="tanggal_masuk" value="Tanggal Masuk" />
                            <TextInput
                                id="tanggal_masuk"
                                type="text"
                                class="mt-1 block w-full bg-gray-100"
                                v-model="tanggalMasukDisplay"
                                readonly
                            />
                            <p class="text-xs text-gray-500 mt-1">Tanggal masuk tidak dapat diubah</p>
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
                            <InputLabel for="imei" value="IMEI" />
                            <TextInput
                                id="imei"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.imei"
                            />
                            <InputError class="mt-2" :message="form.errors.imei" />
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
                            <span v-if="isLoading">Saving...</span>
                            <span v-else>SAVE CHANGES</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Contact Tab -->
            <div v-else-if="activeTab === 'contact'">
                <form @submit.prevent="submitProfile" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Alamat</h3>
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="alamat" value="Alamat" />
                            <textarea v-model="form.alamat" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            <InputError class="mt-2" :message="form.errors.alamat" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="alamat_ktp" value="Alamat KTP" />
                            <textarea v-model="form.alamat_ktp" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            <InputError class="mt-2" :message="form.errors.alamat_ktp" />
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Kontak Darurat</h3>
                        </div>

                        <div>
                            <InputLabel for="nama_kontak_darurat" value="Nama Kontak Darurat" />
                            <TextInput
                                id="nama_kontak_darurat"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_kontak_darurat"
                            />
                            <InputError class="mt-2" :message="form.errors.nama_kontak_darurat" />
                        </div>

                        <div>
                            <InputLabel for="no_hp_kontak_darurat" value="No HP Kontak Darurat" />
                            <TextInput
                                id="no_hp_kontak_darurat"
                                type="tel"
                                class="mt-1 block w-full"
                                v-model="form.no_hp_kontak_darurat"
                            />
                            <InputError class="mt-2" :message="form.errors.no_hp_kontak_darurat" />
                        </div>

                        <div>
                            <InputLabel for="hubungan_kontak_darurat" value="Hubungan Kontak Darurat" />
                            <select v-model="form.hubungan_kontak_darurat" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
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
                            <span v-if="isLoading">Saving...</span>
                            <span v-else>SAVE CHANGES</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Documents Tab -->
            <div v-else-if="activeTab === 'documents'">
                <form @submit.prevent="submitProfile" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="no_ktp" value="No KTP" />
                            <TextInput
                                id="no_ktp"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.no_ktp"
                            />
                            <InputError class="mt-2" :message="form.errors.no_ktp" />
                        </div>

                        <div>
                            <InputLabel for="nomor_kk" value="Nomor KK" />
                            <TextInput
                                id="nomor_kk"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nomor_kk"
                            />
                            <InputError class="mt-2" :message="form.errors.nomor_kk" />
                        </div>

                        <div>
                            <InputLabel for="npwp_number" value="NPWP Number" />
                            <TextInput
                                id="npwp_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.npwp_number"
                            />
                            <InputError class="mt-2" :message="form.errors.npwp_number" />
                        </div>

                        <div>
                            <InputLabel for="bpjs_health_number" value="BPJS Health Number" />
                            <TextInput
                                id="bpjs_health_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.bpjs_health_number"
                            />
                            <InputError class="mt-2" :message="form.errors.bpjs_health_number" />
                        </div>

                        <div>
                            <InputLabel for="bpjs_employment_number" value="BPJS Employment Number" />
                            <TextInput
                                id="bpjs_employment_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.bpjs_employment_number"
                            />
                            <InputError class="mt-2" :message="form.errors.bpjs_employment_number" />
                        </div>

                        <div>
                            <InputLabel for="last_education" value="Pendidikan Terakhir" />
                            <select v-model="form.last_education" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih Pendidikan</option>
                                <option v-for="opt in pendidikanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.last_education" />
                        </div>

                        <div>
                            <InputLabel for="name_school_college" value="Nama Sekolah/Kampus" />
                            <TextInput
                                id="name_school_college"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name_school_college"
                            />
                            <InputError class="mt-2" :message="form.errors.name_school_college" />
                        </div>

                        <div>
                            <InputLabel for="school_college_major" value="Jurusan" />
                            <TextInput
                                id="school_college_major"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.school_college_major"
                            />
                            <InputError class="mt-2" :message="form.errors.school_college_major" />
                        </div>

                        <div>
                            <InputLabel for="nama_rekening" value="Nama Rekening" />
                            <TextInput
                                id="nama_rekening"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.nama_rekening"
                            />
                            <InputError class="mt-2" :message="form.errors.nama_rekening" />
                        </div>

                        <div>
                            <InputLabel for="no_rekening" value="No Rekening" />
                            <TextInput
                                id="no_rekening"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.no_rekening"
                            />
                            <InputError class="mt-2" :message="form.errors.no_rekening" />
                        </div>

                        <!-- File Uploads -->
                        <div>
                            <InputLabel for="foto_ktp" value="Foto KTP" />
                            <input type="file" @change="handleFileChange($event, 'foto_ktp')" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
                            <InputError class="mt-2" :message="form.errors.foto_ktp" />
                        </div>

                        <div>
                            <InputLabel for="foto_kk" value="Foto KK" />
                            <input type="file" @change="handleFileChange($event, 'foto_kk')" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
                            <InputError class="mt-2" :message="form.errors.foto_kk" />
                        </div>

                        <div>
                            <InputLabel for="upload_latest_color_photo" value="Upload Latest Color Photo" />
                            <input type="file" @change="handleFileChange($event, 'upload_latest_color_photo')" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
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
                            <span v-if="isLoading">Saving...</span>
                            <span v-else>SAVE CHANGES</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <div v-else-if="activeTab === 'password'">
                <form @submit.prevent="submitPassword" class="space-y-6">
                    <div>
                        <InputLabel for="current_password" value="Current Password" />
                        <TextInput
                            id="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="passwordForm.current_password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.current_password" />
                    </div>
                    <div>
                        <InputLabel for="password" value="New Password" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="passwordForm.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="passwordForm.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.password_confirmation" />
                    </div>
                    <div class="flex justify-end gap-4">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="passwordForm.processing">
                            Update Password
                        </PrimaryButton>
                    </div>
                </form>
            </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t">
                <div class="flex justify-end">
                    <button @click="emit('close')"
                            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all font-medium">
                        <i class="fa fa-times mr-2"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fade-in {
  from { 
    opacity: 0; 
    transform: translateY(20px) scale(0.95);
  }
  to { 
    opacity: 1; 
    transform: translateY(0) scale(1);
  }
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style> 