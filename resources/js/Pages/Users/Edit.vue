<script setup>
import { ref, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  user: Object,
});

const dropdownData = ref({ outlets: [], jabatans: [], divisions: [] });
const isLoadingDropdown = ref(false);
const isSubmitting = ref(false);
const fieldErrors = ref({});

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
  nik: String(props.user.nik || ''),
  no_ktp: String(props.user.no_ktp || ''),
  nama_lengkap: String(props.user.nama_lengkap || ''),
  email: String(props.user.email || ''),
  password: '',
  hint_password: String(props.user.hint_password || ''),
  nama_panggilan: String(props.user.nama_panggilan || ''),
  jenis_kelamin: String(props.user.jenis_kelamin || ''),
  tempat_lahir: String(props.user.tempat_lahir || ''),
  tanggal_lahir: props.user.tanggal_lahir || '',
  suku: String(props.user.suku || ''),
  agama: String(props.user.agama || ''),
  status_pernikahan: String(props.user.status_pernikahan || ''),
  alamat: String(props.user.alamat || ''),
  alamat_ktp: String(props.user.alamat_ktp || ''),
  foto_ktp: null,
  avatar: null,
  nomor_kk: String(props.user.nomor_kk || ''),
  foto_kk: null,
  no_hp: String(props.user.no_hp || ''),
  id_jabatan: props.user.id_jabatan ? String(props.user.id_jabatan) : '',
  id_outlet: props.user.id_outlet ? String(props.user.id_outlet) : '',
  division_id: props.user.division_id ? String(props.user.division_id) : '',
  imei: String(props.user.imei || ''),
  golongan_darah: String(props.user.golongan_darah || ''),
  nama_rekening: String(props.user.nama_rekening || ''),
  no_rekening: String(props.user.no_rekening || ''),
  nama_kontak_darurat: String(props.user.nama_kontak_darurat || ''),
  no_hp_kontak_darurat: String(props.user.no_hp_kontak_darurat || ''),
  hubungan_kontak_darurat: String(props.user.hubungan_kontak_darurat || ''),
  pin_pos: String(props.user.pin_pos || ''),
  npwp_number: String(props.user.npwp_number || ''),
  bpjs_health_number: String(props.user.bpjs_health_number || ''),
  bpjs_employment_number: String(props.user.bpjs_employment_number || ''),
  last_education: String(props.user.last_education || ''),
  name_school_college: String(props.user.name_school_college || ''),
  school_college_major: String(props.user.school_college_major || ''),
  upload_latest_color_photo: null,
  tanggal_masuk: props.user.tanggal_masuk || '',
});

async function fetchDropdownData() {
  isLoadingDropdown.value = true;
  try {
    console.log('Fetching dropdown data...');
    const response = await axios.get(route('users.dropdown-data'));
    console.log('Dropdown response:', response.data);
    if (response.data.success) {
      dropdownData.value = {
        outlets: response.data.outlets || [],
        jabatans: response.data.jabatans || [],
        divisions: response.data.divisions || [],
      };
      console.log('Dropdown data set:', dropdownData.value);
      console.log('Divisions data:', dropdownData.value.divisions);
      console.log('Sample division:', dropdownData.value.divisions[0]);
    } else {
      console.error('Failed to load dropdown data:', response.data);
    }
  } catch (error) {
    console.error('Error fetching dropdown data:', error);
  } finally {
    isLoadingDropdown.value = false;
  }
}

onMounted(fetchDropdownData);

function handleFileChange(e, field) {
  const file = e.target.files[0];
  console.log(`File selected for ${field}:`, file);
  form[field] = file;
}

async function submit() {
  const confirm = await Swal.fire({
    title: 'Simpan Perubahan?',
    text: 'Apakah Anda yakin ingin menyimpan perubahan data karyawan ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;

  isSubmitting.value = true;
  fieldErrors.value = {}; // Clear previous errors
  
  // Log form data before submission
  console.log('Form data to submit:', form.data());
  console.log('Form errors:', form.errors);
  console.log('Specific fields being sent:', {
    id_jabatan: form.id_jabatan,
    id_outlet: form.id_outlet,
    division_id: form.division_id
  });
  
  // Check if fields are empty strings and convert to null
  if (form.id_jabatan === '') form.id_jabatan = null;
  if (form.id_outlet === '') form.id_outlet = null;
  if (form.division_id === '') form.division_id = null;
  
  console.log('After null conversion:', {
    id_jabatan: form.id_jabatan,
    id_outlet: form.id_outlet,
    division_id: form.division_id
  });
  
  // Check file uploads
  console.log('File uploads:', {
    foto_ktp: form.foto_ktp,
    foto_kk: form.foto_kk,
    upload_latest_color_photo: form.upload_latest_color_photo,
    avatar: form.avatar
  });
  
  // Gunakan Inertia form.put untuk lebih reliable
  console.log('Form data before submission:', form.data());
  console.log('Form data keys:', Object.keys(form.data()));
  console.log('Form data values:', Object.values(form.data()));
  console.log('Form data entries:', Object.entries(form.data()));
  console.log('Form data has files:', {
    foto_ktp: form.foto_ktp instanceof File,
    foto_kk: form.foto_kk instanceof File,
    upload_latest_color_photo: form.upload_latest_color_photo instanceof File,
    avatar: form.avatar instanceof File
  });
  
  // Check if files are actually being sent
  const formData = form.data();
  console.log('Form data files check:', {
    foto_ktp: formData.foto_ktp,
    foto_kk: formData.foto_kk,
    upload_latest_color_photo: formData.upload_latest_color_photo,
    avatar: formData.avatar
  });
  
  // Check if files are null or undefined
  console.log('File null check:', {
    foto_ktp: form.foto_ktp === null,
    foto_kk: form.foto_kk === null,
    upload_latest_color_photo: form.upload_latest_color_photo === null,
    avatar: form.avatar === null
  });
  
  // Check if files are undefined
  console.log('File undefined check:', {
    foto_ktp: form.foto_ktp === undefined,
    foto_kk: form.foto_kk === undefined,
    upload_latest_color_photo: form.upload_latest_color_photo === undefined,
    avatar: form.avatar === undefined
  });
  
  // Check if files are empty strings
  console.log('File empty string check:', {
    foto_ktp: form.foto_ktp === '',
    foto_kk: form.foto_kk === '',
    upload_latest_color_photo: form.upload_latest_color_photo === '',
    avatar: form.avatar === ''
  });
  
  // Check if files are falsy
  console.log('File falsy check:', {
    foto_ktp: !form.foto_ktp,
    foto_kk: !form.foto_kk,
    upload_latest_color_photo: !form.upload_latest_color_photo,
    avatar: !form.avatar
  });
  form.put(route('users.update', props.user.id), {
    onSuccess: () => {
      console.log('Update successful');
      Swal.fire('Berhasil', 'Data karyawan berhasil diupdate!', 'success').then(() => {
        router.visit(`/users/${props.user.id}`);
      });
    },
    onError: (errors) => {
      console.error('Update failed with errors:', errors);
      fieldErrors.value = errors;
      
      // Create detailed error message for SweetAlert
      let errorMessage = '<div class="text-left">';
      errorMessage += '<p class="font-semibold text-red-600 mb-3">Gagal menyimpan data karyawan. Periksa field berikut:</p>';
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
        title: 'Gagal Menyimpan Data',
        html: errorMessage,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'OK',
        width: '500px'
      });
      
      console.error('Update errors:', errors);
    },
    onFinish: () => {
      console.log('Update finished');
      isSubmitting.value = false;
    },
    preserveScroll: true,
  });
}

function cancel() {
  router.visit('/users');
}

function getFieldDisplayName(field) {
  const fieldNames = {
    'nik': 'NIK',
    'no_ktp': 'No KTP',
    'nama_lengkap': 'Nama Lengkap',
    'email': 'Email',
    'password': 'Password',
    'hint_password': 'Hint Password',
    'nama_panggilan': 'Nama Panggilan',
    'jenis_kelamin': 'Jenis Kelamin',
    'tempat_lahir': 'Tempat Lahir',
    'tanggal_lahir': 'Tanggal Lahir',
    'suku': 'Suku',
    'agama': 'Agama',
    'status_pernikahan': 'Status Pernikahan',
    'alamat': 'Alamat',
    'alamat_ktp': 'Alamat KTP',
    'foto_ktp': 'Foto KTP',
    'avatar': 'Avatar',
    'nomor_kk': 'Nomor KK',
    'foto_kk': 'Foto KK',
    'no_hp': 'No HP',
    'id_jabatan': 'Jabatan',
    'id_outlet': 'Outlet',
    'division_id': 'Divisi',
    'imei': 'IMEI',
    'golongan_darah': 'Golongan Darah',
    'nama_rekening': 'Nama Rekening',
    'no_rekening': 'No Rekening',
    'nama_kontak_darurat': 'Nama Kontak Darurat',
    'no_hp_kontak_darurat': 'No HP Kontak Darurat',
    'hubungan_kontak_darurat': 'Hubungan Kontak Darurat',
    'pin_pos': 'PIN POS',
    'npwp_number': 'NPWP Number',
    'bpjs_health_number': 'BPJS Health Number',
    'bpjs_employment_number': 'BPJS Employment Number',
    'last_education': 'Pendidikan Terakhir',
    'name_school_college': 'Nama Sekolah/Kampus',
    'school_college_major': 'Jurusan',
    'upload_latest_color_photo': 'Upload Latest Color Photo',
    'tanggal_masuk': 'Tanggal Masuk'
  };
  
  return fieldNames[field] || field;
}
</script>

<template>
  <AppLayout title="Edit Karyawan">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800">Edit Karyawan</h1>
      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- NIK - Readonly -->
        <div>
          <label class="block text-sm font-medium text-gray-700">NIK</label>
          <input v-model="form.nik" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm bg-gray-100" readonly />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">No KTP</label>
          <input v-model="form.no_ktp" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="50" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Lengkap *</label>
          <input v-model="form.nama_lengkap" :class="[
            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm',
            fieldErrors.nama_lengkap ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'
          ]" required maxlength="255" />
          <p v-if="fieldErrors.nama_lengkap" class="text-red-500 text-sm mt-1">{{ fieldErrors.nama_lengkap }}</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Panggilan</label>
          <input v-model="form.nama_panggilan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input v-model="form.email" type="email" :class="[
            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm',
            fieldErrors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'
          ]" maxlength="255" />
          <p v-if="fieldErrors.email" class="text-red-500 text-sm mt-1">{{ fieldErrors.email }}</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Password (kosongkan jika tidak diubah)</label>
          <input v-model="form.password" type="password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Hint Password</label>
          <input v-model="form.hint_password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Jenis Kelamin</label>
          <select v-model="form.jenis_kelamin" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Jenis Kelamin</option>
            <option v-for="opt in jenisKelaminOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Tempat Lahir</label>
          <input v-model="form.tempat_lahir" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Tanggal Lahir</label>
          <input v-model="form.tanggal_lahir" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Suku</label>
          <input v-model="form.suku" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="50" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Agama</label>
          <select v-model="form.agama" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Agama</option>
            <option v-for="opt in agamaOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Status Pernikahan</label>
          <select v-model="form.status_pernikahan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Status</option>
            <option v-for="opt in statusPernikahanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Golongan Darah</label>
          <select v-model="form.golongan_darah" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Golongan Darah</option>
            <option v-for="opt in golonganDarahOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">No HP</label>
          <input v-model="form.no_hp" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="15" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">IMEI</label>
          <input v-model="form.imei" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="50" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Jabatan</label>
          <select v-model="form.id_jabatan" :disabled="isLoadingDropdown" :class="[
            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm',
            fieldErrors.id_jabatan ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'
          ]">
            <option value="">Pilih Jabatan</option>
            <option v-for="jabatan in dropdownData.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">{{ jabatan.nama_jabatan }}</option>
          </select>
          <p v-if="fieldErrors.id_jabatan" class="text-red-500 text-sm mt-1">{{ fieldErrors.id_jabatan }}</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Outlet</label>
          <select v-model="form.id_outlet" :disabled="isLoadingDropdown" :class="[
            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm',
            fieldErrors.id_outlet ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'
          ]">
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in dropdownData.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
          </select>
          <p v-if="fieldErrors.id_outlet" class="text-red-500 text-sm mt-1">{{ fieldErrors.id_outlet }}</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Divisi</label>
          <select v-model="form.division_id" :disabled="isLoadingDropdown" :class="[
            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm',
            fieldErrors.division_id ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'
          ]">
            <option value="">Pilih Divisi</option>
            <option v-for="division in dropdownData.divisions" :key="division.id_division" :value="division.id_division">{{ division.nama_division }}</option>
          </select>
          <p v-if="fieldErrors.division_id" class="text-red-500 text-sm mt-1">{{ fieldErrors.division_id }}</p>
          <p v-if="dropdownData.divisions.length === 0 && !isLoadingDropdown" class="text-xs text-gray-500 mt-1">Tidak ada data divisi tersedia</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Tanggal Masuk</label>
          <input v-model="form.tanggal_masuk" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">PIN POS</label>
          <input v-model="form.pin_pos" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="10" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Alamat</label>
          <textarea v-model="form.alamat" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Alamat KTP</label>
          <textarea v-model="form.alamat_ktp" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nomor KK</label>
          <input v-model="form.nomor_kk" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="50" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Rekening</label>
          <input v-model="form.nama_rekening" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">No Rekening</label>
          <input v-model="form.no_rekening" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="50" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">NPWP Number</label>
          <input v-model="form.npwp_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="100" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">BPJS Health Number</label>
          <input v-model="form.bpjs_health_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="100" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">BPJS Employment Number</label>
          <input v-model="form.bpjs_employment_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="100" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Pendidikan Terakhir</label>
          <select v-model="form.last_education" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Pendidikan</option>
            <option v-for="opt in pendidikanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Sekolah/Kampus</label>
          <input v-model="form.name_school_college" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Jurusan</label>
          <input v-model="form.school_college_major" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Kontak Darurat</label>
          <input v-model="form.nama_kontak_darurat" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">No HP Kontak Darurat</label>
          <input v-model="form.no_hp_kontak_darurat" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="15" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Hubungan Kontak Darurat</label>
          <select v-model="form.hubungan_kontak_darurat" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Hubungan</option>
            <option v-for="opt in hubunganKontakOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        
        <!-- File Uploads -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Avatar</label>
          <input type="file" @change="handleFileChange($event, 'avatar')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB. Kosongkan jika tidak diubah</p>
          <div v-if="user.avatar" class="mt-2">
            <p class="text-xs text-blue-600">File saat ini: {{ user.avatar }}</p>
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Foto KTP</label>
          <input type="file" @change="handleFileChange($event, 'foto_ktp')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB. Kosongkan jika tidak diubah</p>
          <div v-if="user.foto_ktp" class="mt-2">
            <p class="text-xs text-blue-600">File saat ini: {{ user.foto_ktp }}</p>
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Foto KK</label>
          <input type="file" @change="handleFileChange($event, 'foto_kk')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB. Kosongkan jika tidak diubah</p>
          <div v-if="user.foto_kk" class="mt-2">
            <p class="text-xs text-blue-600">File saat ini: {{ user.foto_kk }}</p>
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Upload Latest Color Photo</label>
          <input type="file" @change="handleFileChange($event, 'upload_latest_color_photo')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB. Kosongkan jika tidak diubah</p>
          <div v-if="user.upload_latest_color_photo" class="mt-2">
            <p class="text-xs text-blue-600">File saat ini: {{ user.upload_latest_color_photo }}</p>
          </div>
        </div>
        
        <div class="md:col-span-2 flex justify-end gap-2 mt-6">
          <button type="button" @click="cancel" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">Batal</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" :disabled="isSubmitting">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 