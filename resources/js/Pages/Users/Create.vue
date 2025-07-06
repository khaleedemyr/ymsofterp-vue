<script setup>
import { ref, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const dropdownData = ref({ outlets: [], jabatans: [], divisions: [] });
const isLoadingDropdown = ref(false);
const isSubmitting = ref(false);

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
  nik: 'Akan di-generate otomatis',
  no_ktp: '',
  nama_lengkap: '',
  email: '',
  password: '',
  hint_password: '',
  nama_panggilan: '',
  jenis_kelamin: '',
  tempat_lahir: '',
  tanggal_lahir: '',
  suku: '',
  agama: '',
  status_pernikahan: '',
  alamat: '',
  alamat_ktp: '',
  foto_ktp: null,
  nomor_kk: '',
  foto_kk: null,
  no_hp: '',
  id_jabatan: '',
  id_outlet: '',
  division_id: '',
  imei: '',
  golongan_darah: '',
  nama_rekening: '',
  no_rekening: '',
  nama_kontak_darurat: '',
  no_hp_kontak_darurat: '',
  hubungan_kontak_darurat: '',
  pin_pos: '',
  npwp_number: '',
  bpjs_health_number: '',
  bpjs_employment_number: '',
  last_education: '',
  name_school_college: '',
  school_college_major: '',
  upload_latest_color_photo: null,
  tanggal_masuk: '',
});

async function fetchDropdownData() {
  isLoadingDropdown.value = true;
  try {
    console.log('Fetching dropdown data...');
    
    // Try the test route first
    try {
      const testResponse = await axios.get('/test/users-dropdown');
      console.log('Test route response:', testResponse.data);
    } catch (testError) {
      console.error('Test route error:', testError);
    }
    
    const response = await axios.get(route('users.dropdown-data'));
    console.log('Dropdown response:', response.data);
    if (response.data.success) {
      dropdownData.value = {
        outlets: response.data.outlets || [],
        jabatans: response.data.jabatans || [],
        divisions: response.data.divisions || [],
      };
      console.log('Dropdown data set:', dropdownData.value);
    } else {
      console.error('Failed to load dropdown data:', response.data);
    }
  } catch (error) {
    console.error('Error fetching dropdown data:', error);
    console.error('Error details:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status,
      headers: error.response?.headers
    });
  } finally {
    isLoadingDropdown.value = false;
  }
}

onMounted(fetchDropdownData);

function handleFileChange(e, field) {
  form[field] = e.target.files[0];
}

async function submit() {
  const confirm = await Swal.fire({
    title: 'Simpan Data?',
    text: 'Apakah Anda yakin ingin menyimpan data karyawan ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;

  const fd = new FormData();
  Object.entries(form).forEach(([key, value]) => {
    if (value !== null && value !== undefined) fd.append(key, value);
  });
  console.log('FormData to send:', Array.from(fd.entries()));

  isSubmitting.value = true;
  try {
    await axios.post(route('users.store'), fd, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    Swal.fire('Berhasil', 'Karyawan berhasil ditambahkan!', 'success').then(() => router.visit('/users'));
  } catch (e) {
    Swal.fire('Gagal', 'Gagal menyimpan data karyawan!', 'error');
    console.error('Create error:', e);
  } finally {
    isSubmitting.value = false;
  }
}

function cancel() {
  router.visit('/users');
}
</script>

<template>
  <AppLayout title="Tambah Karyawan">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800">Tambah Karyawan</h1>
      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- NIK - Readonly -->
        <div>
          <label class="block text-sm font-medium text-gray-700">NIK</label>
          <input v-model="form.nik" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm bg-gray-100" readonly />
          <p class="text-xs text-gray-500 mt-1">NIK akan di-generate otomatis</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">No KTP</label>
          <input v-model="form.no_ktp" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="50" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Lengkap *</label>
          <input v-model="form.nama_lengkap" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Panggilan</label>
          <input v-model="form.nama_panggilan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" maxlength="255" />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Password</label>
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
          <select v-model="form.id_jabatan" :disabled="isLoadingDropdown" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Jabatan</option>
            <option v-for="jabatan in dropdownData.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">{{ jabatan.nama_jabatan }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Outlet</label>
          <select v-model="form.id_outlet" :disabled="isLoadingDropdown" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in dropdownData.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Divisi</label>
          <select v-model="form.division_id" :disabled="isLoadingDropdown" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">Pilih Divisi</option>
            <option v-for="division in dropdownData.divisions" :key="division.id_division" :value="division.id_division">{{ division.nama_division }}</option>
          </select>
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
          <label class="block text-sm font-medium text-gray-700">Foto KTP</label>
          <input type="file" @change="handleFileChange($event, 'foto_ktp')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Foto KK</label>
          <input type="file" @change="handleFileChange($event, 'foto_kk')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Upload Latest Color Photo</label>
          <input type="file" @change="handleFileChange($event, 'upload_latest_color_photo')" accept="image/*" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 2MB</p>
        </div>
        
        <div class="md:col-span-2 flex justify-end gap-2 mt-6">
          <button type="button" @click="cancel" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">Batal</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" :disabled="isSubmitting">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 