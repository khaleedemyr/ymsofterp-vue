<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserFormModal from './UserFormModal.vue';
import axios from 'axios';
import PinManagementModal from './PinManagementModal.vue';
import ActivationModal from './ActivationModal.vue';
import SaldoModal from './SaldoModal.vue';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  users: Object, // { data, links, meta }
  filters: Object,
  outlets: Array,
  divisions: Array,
  jabatans: Array,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      inactive: 0,
      new: 0
    })
  },
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedUser = ref(null);
const dropdownData = ref({ outlets: [], jabatans: [] });
const isLoadingDropdown = ref(false);
const showPinModal = ref(false);
const pinUserId = ref(null);
const pinUserName = ref('');
const showActivationModal = ref(false);
const selectedUserForActivation = ref(null);
const showSaldoModal = ref(false);
const selectedUserForSaldo = ref(null);
const outletId = ref(props.filters?.outlet_id || '');
const divisionId = ref(props.filters?.division_id || '');
const status = ref(props.filters?.status || 'A');
const perPage = ref(props.filters?.per_page || 15);
const viewMode = ref(localStorage.getItem('userViewMode') || 'list'); // 'list' or 'card'
const showUploadModal = ref(false);
const selectedFile = ref(null);
const uploading = ref(false);

// Lightbox state
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

const debouncedSearch = debounce(() => {
  router.get('/users', {
    search: search.value,
    outlet_id: outletId.value,
    division_id: divisionId.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

// Watch for filter changes
watch([outletId, divisionId, status, perPage], () => {
  router.get('/users', {
    search: search.value,
    outlet_id: outletId.value,
    division_id: divisionId.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});

function goToPage(url) {
  if (url) {
    // Parse URL to add current filter parameters
    const urlObj = new URL(url, window.location.origin);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('outlet_id', outletId.value);
    urlObj.searchParams.set('division_id', divisionId.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

async function fetchDropdownData() {
  isLoadingDropdown.value = true;
  try {
    const response = await axios.get(route('users.dropdown-data'));
    if (response.data.success) {
      dropdownData.value = {
        outlets: response.data.outlets || [],
        jabatans: response.data.jabatans || [],
      };
    }
  } finally {
    isLoadingDropdown.value = false;
  }
}

function openCreate() {
  router.visit('/users/create');
}

function openEdit(user) {
  router.visit(`/users/${user.id}/edit`);
}

function openShow(user) {
  router.visit(`/users/${user.id}`);
}

function openPinModal(user) {
  pinUserId.value = user.id;
  pinUserName.value = user.nama_lengkap;
  showPinModal.value = true;
}

function closePinModal() {
  showPinModal.value = false;
}

async function hapus(user) {
  const result = await Swal.fire({
    title: 'Nonaktifkan Karyawan?',
    text: `Yakin ingin menonaktifkan karyawan "${user.nama_lengkap}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Nonaktifkan!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('users.destroy', user.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Karyawan berhasil dinonaktifkan!', 'success'),
  });
}

async function toggleStatus(user) {
  // Jika status 'B' (Baru), tampilkan modal aktivasi
  if (user.status === 'B') {
    selectedUserForActivation.value = user;
    showActivationModal.value = true;
    return;
  }

  // Jika status 'A' atau 'N' dan field yang diperlukan masih null, tampilkan modal aktivasi
  if ((user.status === 'A' || user.status === 'N') && 
      (!user.id_outlet || !user.id_jabatan || !user.division_id)) {
    selectedUserForActivation.value = user;
    showActivationModal.value = true;
    return;
  }

  // Untuk status lain, gunakan logika lama
  const action = user.status === 'A' ? 'menonaktifkan' : 'mengaktifkan';
  const result = await Swal.fire({
    title: `${user.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'} Karyawan?`,
    text: `Yakin ingin ${action} karyawan "${user.nama_lengkap}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${user.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.patch(route('users.toggle-status', user.id));
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status karyawan', 'error');
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function closeActivationModal() {
  showActivationModal.value = false;
  selectedUserForActivation.value = null;
}

function openSaldoModal(user) {
  selectedUserForSaldo.value = user;
  showSaldoModal.value = true;
}

function closeSaldoModal() {
  showSaldoModal.value = false;
  selectedUserForSaldo.value = null;
}

function onActivationSuccess(message) {
  Swal.fire('Berhasil', message, 'success');
  reload();
}

function onSaldoSuccess(message) {
  Swal.fire('Berhasil', message, 'success');
  reload();
}

function filterByStatus(newStatus) {
  status.value = newStatus;
}

function toggleViewMode() {
  viewMode.value = viewMode.value === 'list' ? 'card' : 'list';
  localStorage.setItem('userViewMode', viewMode.value);
}

// Avatar and lightbox functions
function getInitials(name) {
  if (!name) return '?';
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().substring(0, 2);
}

function getImageUrl(imagePath) {
  if (!imagePath) return null;
  try {
    return `/storage/${imagePath}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
}

function openImageModal(imageUrl) {
  if (!imageUrl) return;
  
  lightboxImages.value = [imageUrl];
  lightboxIndex.value = 0;
  lightboxVisible.value = true;
}

function openUploadModal() {
  showUploadModal.value = true;
}

function closeUploadModal() {
  showUploadModal.value = false;
}

function handleFileSelect(event) {
  const file = event.target.files[0];
  if (file) {
    // Validate file type
    const allowedTypes = [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
      'application/vnd.ms-excel' // .xls
    ];
    
    if (!allowedTypes.includes(file.type)) {
      Swal.fire({
        icon: 'error',
        title: 'Format File Salah',
        text: 'File harus berformat Excel (.xlsx atau .xls)',
        confirmButtonColor: '#ef4444'
      });
      clearFile();
      return;
    }
    
    // Validate file size (10MB)
    if (file.size > 10 * 1024 * 1024) {
      Swal.fire({
        icon: 'error',
        title: 'Ukuran File Terlalu Besar',
        text: 'Ukuran file maksimal 10MB',
        confirmButtonColor: '#ef4444'
      });
      clearFile();
      return;
    }
    
    selectedFile.value = file;
    
    // Show success message
    Swal.fire({
      icon: 'success',
      title: 'File Berhasil Dipilih',
      html: `
        <div class="text-left">
          <p><strong>Nama File:</strong> ${file.name}</p>
          <p><strong>Ukuran:</strong> ${formatFileSize(file.size)}</p>
          <p><strong>Format:</strong> Excel</p>
        </div>
      `,
      confirmButtonColor: '#10b981',
      timer: 2000,
      showConfirmButton: false
    });
  }
}

function clearFile() {
  selectedFile.value = null;
  if (document.querySelector('input[type="file"]')) {
    document.querySelector('input[type="file"]').value = '';
  }
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function uploadFile() {
  if (!selectedFile.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih file terlebih dahulu!',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  // Konfirmasi sebelum upload
  Swal.fire({
    title: 'Konfirmasi Upload',
    html: `
      <div class="text-left">
        <p>Anda akan mengupload file:</p>
        <p class="font-semibold text-blue-600">${selectedFile.value.name}</p>
        <p class="text-sm text-gray-500 mt-2">Pastikan data di Excel sudah benar sebelum melanjutkan.</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Upload!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      performUpload();
    }
  });
}

function performUpload() {
  uploading.value = true;
  console.log('Starting upload process...');
  
  const formData = new FormData();
  formData.append('file', selectedFile.value);
  console.log('FormData created, file:', selectedFile.value?.name);

  router.post('/employee-upload', formData, {
    forceFormData: true,
    onStart: () => {
      console.log('Upload request started');
    },
    onProgress: (progress) => {
      console.log('Upload progress:', progress);
    },
    onSuccess: (page) => {
      console.log('Upload success response:', page);
      uploading.value = false;
      clearFile();
      closeUploadModal();
      
      // Show success message with details
      console.log('Flash messages:', page.props.flash);
      if (page.props.flash.success) {
        const details = page.props.flash.success.details;
        
        if (details && details.errors && details.errors.length > 0) {
          // Ada error, tampilkan dengan detail
          let errorMessage = `<div class="text-left">`;
          errorMessage += `<p><strong>Total data diproses:</strong> ${details.total_processed}</p>`;
          errorMessage += `<p><strong>Berhasil diupdate:</strong> ${details.successful_updates}</p>`;
          errorMessage += `<p><strong>Error:</strong> ${details.errors.length}</p>`;
          errorMessage += `<br><p><strong>Detail Error:</strong></p>`;
          errorMessage += `<ul class="list-disc list-inside text-sm">`;
          details.errors.forEach(error => {
            errorMessage += `<li class="text-red-600">${error}</li>`;
          });
          errorMessage += `</ul></div>`;
          
          Swal.fire({
            icon: 'warning',
            title: 'Upload Selesai dengan Error',
            html: errorMessage,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'OK'
          });
        } else {
          // Berhasil semua
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            html: `
              <div class="text-left">
                <p><strong>Total data diproses:</strong> ${details?.total_processed || 0}</p>
                <p><strong>Berhasil diupdate:</strong> ${details?.successful_updates || 0}</p>
                <p><strong>Error:</strong> 0</p>
              </div>
            `,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'OK'
          });
        }
        
        reload(); // Reload the page to show updated data
      } else {
        // Fallback: no flash message but request was successful
        console.log('No flash message found, but request was successful');
        Swal.fire({
          icon: 'success',
          title: 'Upload Berhasil',
          text: 'File berhasil diupload, namun tidak ada detail yang tersedia.',
          confirmButtonText: 'OK'
        });
        reload();
      }
    },
    onError: (errors) => {
      uploading.value = false;
      console.error('Upload error:', errors);
      console.error('Error details:', JSON.stringify(errors, null, 2));
      
      // Check if it's a flash error message
      if (errors && errors.flash && errors.flash.error) {
        console.log('Flash error found:', errors.flash.error);
        Swal.fire({
          icon: 'error',
          title: 'Upload Gagal',
          text: errors.flash.error,
          confirmButtonText: 'OK'
        });
        return;
      }
      
      let errorMessage = '<div class="text-left">';
      errorMessage += '<p>Terjadi kesalahan saat mengupload file:</p>';
      errorMessage += '<ul class="list-disc list-inside text-sm mt-2">';
      
      if (typeof errors === 'object') {
        Object.keys(errors).forEach(key => {
          if (Array.isArray(errors[key])) {
            errors[key].forEach(error => {
              errorMessage += `<li class="text-red-600">${error}</li>`;
            });
          } else {
            errorMessage += `<li class="text-red-600">${errors[key]}</li>`;
          }
        });
      } else {
        errorMessage += `<li class="text-red-600">${errors}</li>`;
      }
      
      errorMessage += '</ul></div>';
      
      Swal.fire({
        icon: 'error',
        title: 'Upload Gagal',
        html: errorMessage,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'OK'
      });
    },
    onFinish: () => {
      console.log('Upload request finished - checking for any unhandled responses');
      // Fallback: if we reach here without success/error, something went wrong
      setTimeout(() => {
        if (uploading.value) {
          console.log('Upload seems to be stuck, forcing stop');
          uploading.value = false;
          Swal.fire({
            icon: 'warning',
            title: 'Upload Timeout',
            text: 'Upload sepertinya mengalami timeout. Silakan coba lagi.',
            confirmButtonText: 'OK'
          });
        }
      }, 30000); // 30 second timeout
    }
  });
}

function downloadTemplate() {
  window.open('/employee-upload/template', '_blank');
}

function exportToExcel() {
  // Build query parameters from current filters
  const params = new URLSearchParams();
  
  if (search.value) params.append('search', search.value);
  if (outletId.value) params.append('outlet_id', outletId.value);
  if (divisionId.value) params.append('division_id', divisionId.value);
  if (status.value && status.value !== 'A') params.append('status', status.value);
  
  // Create export URL with current filters
  const exportUrl = `/users/export?${params.toString()}`;
  
  // Open export URL in new tab to trigger download
  window.open(exportUrl, '_blank');
}
</script>

<template>
  <AppLayout title="Data Karyawan">
    <div class="w-full py-8 px-4">
             <div class="flex justify-between items-center mb-6">
         <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
           <i class="fa-solid fa-users text-blue-500"></i> Data Karyawan
         </h1>
         <div class="flex gap-2">
           <button @click="toggleViewMode" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
             <i :class="viewMode === 'list' ? 'fa-solid fa-th-large mr-2' : 'fa-solid fa-list mr-2'"></i>
             {{ viewMode === 'list' ? 'Card View' : 'List View' }}
           </button>
           <button @click="exportToExcel" class="bg-gradient-to-r from-orange-500 to-orange-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
             <i class="fa-solid fa-file-excel mr-2"></i>Export Excel
           </button>
           <button @click="openUploadModal" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
             <i class="fa-solid fa-upload mr-2"></i>Upload Excel
           </button>
           <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
             + Tambah Karyawan Baru
           </button>
         </div>
       </div>

       <!-- Statistics Cards -->
       <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
         <!-- Total Karyawan -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'all' ? 'bg-blue-50 border-blue-500 shadow-xl' : 'bg-white border-blue-500 hover:shadow-xl'
         ]" @click="filterByStatus('all')" title="Klik untuk melihat semua karyawan">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Total Karyawan</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
               <p class="text-xs text-gray-500">100% dari total</p>
             </div>
             <div class="bg-blue-100 p-3 rounded-full">
               <i class="fa-solid fa-users text-blue-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>

         <!-- Karyawan Aktif -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'A' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
         ]" @click="filterByStatus('A')" title="Klik untuk melihat karyawan aktif">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Karyawan Aktif</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.active }}</p>
               <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.active / statistics.total) * 100) : 0 }}% dari total</p>
             </div>
             <div class="bg-green-100 p-3 rounded-full">
               <i class="fa-solid fa-user-check text-green-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>

         <!-- Karyawan Non-Aktif -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'N' ? 'bg-red-50 border-red-500 shadow-xl' : 'bg-white border-red-500 hover:shadow-xl'
         ]" @click="filterByStatus('N')" title="Klik untuk melihat karyawan non-aktif">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Karyawan Non-Aktif</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.inactive }}</p>
               <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.inactive / statistics.total) * 100) : 0 }}% dari total</p>
             </div>
             <div class="bg-red-100 p-3 rounded-full">
               <i class="fa-solid fa-user-slash text-red-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>

         <!-- Karyawan Baru -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'B' ? 'bg-yellow-50 border-yellow-500 shadow-xl' : 'bg-white border-yellow-500 hover:shadow-xl'
         ]" @click="filterByStatus('B')" title="Klik untuk melihat karyawan baru">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Karyawan Baru</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.new }}</p>
               <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.new / statistics.total) * 100) : 0 }}% dari total</p>
             </div>
             <div class="bg-yellow-100 p-3 rounded-full">
               <i class="fa-solid fa-user-plus text-yellow-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>
       </div>
      <div class="mb-4 flex gap-4 flex-wrap">
        <select v-model="status" class="form-input rounded-xl">
          <option value="A">Aktif</option>
          <option value="N">Non-Aktif</option>
          <option value="B">Baru</option>
          <option value="all">Semua Status</option>
        </select>
        <select v-model="outletId" class="form-input rounded-xl">
          <option value="">Semua Outlet</option>
          <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
        </select>
        <select v-model="divisionId" class="form-input rounded-xl">
          <option value="">Semua Divisi</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
        </select>
        <select v-model="perPage" class="form-input rounded-xl">
          <option value="10">10 per halaman</option>
          <option value="15">15 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari NIK, No KTP, Nama, Email, No HP..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition min-w-64"
        />
      </div>
      <!-- List View -->
      <div v-if="viewMode === 'list'" class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Avatar</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No KTP</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No HP</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users.data" :key="user.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <!-- Avatar with lightbox functionality -->
                <div v-if="user.avatar" class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-200 shadow-md cursor-pointer hover:shadow-lg transition-all" @click="openImageModal(getImageUrl(user.avatar))">
                  <img :src="getImageUrl(user.avatar)" :alt="user.nama_lengkap" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                </div>
                <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold border-2 border-blue-200 shadow-md">
                  {{ getInitials(user.nama_lengkap) }}
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.nik }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.no_ktp }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ user.nama_lengkap }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.nama_jabatan || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.nama_outlet || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.email }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.no_hp }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  user.status === 'A' ? 'bg-green-100 text-green-800' : 
                  user.status === 'N' ? 'bg-red-100 text-red-800' : 
                  'bg-yellow-100 text-yellow-800'
                ]">
                  {{ user.status === 'A' ? 'Aktif' : user.status === 'N' ? 'Non-Aktif' : 'Baru' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(user)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(user)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="toggleStatus(user)" :class="[
                  'px-2 py-1 rounded transition',
                  user.status === 'A' ? 'bg-red-500 text-white hover:bg-red-600' : 
                  user.status === 'B' ? 'bg-blue-500 text-white hover:bg-blue-600' : 
                  'bg-green-500 text-white hover:bg-green-600'
                ]" :title="user.status === 'A' ? 'Nonaktifkan' : user.status === 'B' ? 'Aktifkan' : 'Aktifkan'">
                  <i :class="user.status === 'A' ? 'fa-solid fa-user-slash' : 'fa-solid fa-user-check'"></i>
                </button>
                <button @click="openPinModal(user)" class="px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition" title="Kelola PIN">
                  <i class="fa-solid fa-key"></i>
                </button>
                <button @click="openSaldoModal(user)" class="px-2 py-1 rounded bg-purple-100 text-purple-700 hover:bg-purple-200 transition" title="Input Saldo">
                  <i class="fa-solid fa-coins"></i>
                </button>
              </td>
            </tr>
            <tr v-if="users.data.length === 0">
              <td colspan="10" class="text-center py-8 text-gray-400">Tidak ada data karyawan</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Card View -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div v-for="user in users.data" :key="user.id" class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100">
          <!-- Card Header with Avatar -->
          <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6 text-center">
            <div v-if="user.avatar" class="w-20 h-20 rounded-full overflow-hidden border-4 border-white shadow-lg cursor-pointer hover:shadow-xl transition-all mx-auto" @click="openImageModal(getImageUrl(user.avatar))">
              <img :src="getImageUrl(user.avatar)" :alt="user.nama_lengkap" class="w-full h-full object-cover hover:scale-105 transition-transform" />
            </div>
            <div v-else class="w-20 h-20 rounded-full bg-white bg-opacity-20 flex items-center justify-center text-white text-2xl font-bold border-4 border-white shadow-lg mx-auto">
              {{ getInitials(user.nama_lengkap) }}
            </div>
            <h3 class="text-white font-bold text-lg mt-3">{{ user.nama_lengkap }}</h3>
            <p class="text-blue-100 text-sm">{{ user.nama_jabatan || 'Jabatan tidak tersedia' }}</p>
          </div>

          <!-- Card Body -->
          <div class="p-6">
            <div class="space-y-3">
              <div class="flex items-center">
                <i class="fa-solid fa-id-card text-gray-400 w-5"></i>
                <span class="ml-3 text-sm text-gray-600">{{ user.nik }}</span>
              </div>
              <div class="flex items-center">
                <i class="fa-solid fa-building text-gray-400 w-5"></i>
                <span class="ml-3 text-sm text-gray-600">{{ user.nama_outlet || 'Outlet tidak tersedia' }}</span>
              </div>
              <div class="flex items-center">
                <i class="fa-solid fa-envelope text-gray-400 w-5"></i>
                <span class="ml-3 text-sm text-gray-600 truncate">{{ user.email }}</span>
              </div>
              <div class="flex items-center">
                <i class="fa-solid fa-phone text-gray-400 w-5"></i>
                <span class="ml-3 text-sm text-gray-600">{{ user.no_hp || 'No HP tidak tersedia' }}</span>
              </div>
            </div>

            <!-- Status Badge -->
            <div class="mt-4 text-center">
              <span :class="[
                'px-3 py-1 rounded-full text-xs font-semibold',
                user.status === 'A' ? 'bg-green-100 text-green-800' : 
                user.status === 'N' ? 'bg-red-100 text-red-800' : 
                'bg-yellow-100 text-yellow-800'
              ]">
                {{ user.status === 'A' ? 'Aktif' : user.status === 'N' ? 'Non-Aktif' : 'Baru' }}
              </span>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-center gap-2">
              <button @click="openShow(user)" class="px-3 py-2 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition text-sm" title="Detail">
                <i class="fa-solid fa-eye"></i>
              </button>
              <button @click="openEdit(user)" class="px-3 py-2 rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition text-sm" title="Edit">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
              <button @click="toggleStatus(user)" :class="[
                'px-3 py-2 rounded-lg transition text-sm',
                user.status === 'A' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 
                user.status === 'B' ? 'bg-blue-100 text-blue-700 hover:bg-blue-200' : 
                'bg-green-100 text-green-700 hover:bg-green-200'
              ]" :title="user.status === 'A' ? 'Nonaktifkan' : user.status === 'B' ? 'Aktifkan' : 'Aktifkan'">
                <i :class="user.status === 'A' ? 'fa-solid fa-user-slash' : 'fa-solid fa-user-check'"></i>
              </button>
              <button @click="openPinModal(user)" class="px-3 py-2 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition text-sm" title="Kelola PIN">
                <i class="fa-solid fa-key"></i>
              </button>
              <button @click="openSaldoModal(user)" class="px-3 py-2 rounded-lg bg-purple-100 text-purple-700 hover:bg-purple-200 transition text-sm" title="Input Saldo">
                <i class="fa-solid fa-coins"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State for Card View -->
        <div v-if="users.data.length === 0" class="col-span-full text-center py-12">
          <div class="text-gray-400 text-lg">
            <i class="fa-solid fa-users text-4xl mb-4"></i>
            <p>Tidak ada data karyawan</p>
          </div>
        </div>
      </div>
      <div class="mt-4 flex justify-between items-center">
        <div class="text-sm text-gray-600">
          Menampilkan {{ users.from || 0 }} - {{ users.to || 0 }} dari {{ users.total || 0 }} data
        </div>
        <nav v-if="users.links && users.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in users.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 hover:bg-blue-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
    <UserFormModal :show="showModal" :mode="modalMode" :user="selectedUser" :dropdownData="dropdownData" :isLoadingDropdown="isLoadingDropdown" @close="closeModal" />
    <PinManagementModal :show="showPinModal" :user-id="pinUserId" :user-name="pinUserName" @close="closePinModal" />
    <ActivationModal 
      :show="showActivationModal" 
      :user="selectedUserForActivation" 
      :jabatans="jabatans" 
      :divisions="divisions" 
      :outlets="outlets" 
      @close="closeActivationModal" 
      @success="onActivationSuccess" 
    />
    <SaldoModal 
      :show="showSaldoModal" 
      :user="selectedUserForSaldo" 
      @close="closeSaldoModal" 
      @success="onSaldoSuccess" 
    />
    
    <!-- Lightbox for Avatar Images -->
    <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
    />

    <!-- Upload Modal -->
    <div v-if="showUploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto relative">
        <!-- Loading Overlay -->
        <div v-if="uploading" class="absolute inset-0 bg-white bg-opacity-90 flex items-center justify-center rounded-2xl z-10">
          <div class="text-center">
            <i class="fa fa-spinner fa-spin text-4xl text-green-500 mb-4"></i>
            <p class="text-lg font-semibold text-gray-700">Memproses File Excel...</p>
            <p class="text-sm text-gray-500 mt-2">Mohon tunggu, sedang mengupdate data karyawan</p>
          </div>
        </div>
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-upload text-green-500"></i> Upload Data Karyawan
          </h2>
          <button @click="closeUploadModal" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>

        <!-- Upload Form -->
        <form @submit.prevent="uploadFile" enctype="multipart/form-data" class="space-y-6">
          <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-green-400 transition-colors">
            <div class="space-y-4">
              <i class="fa-solid fa-file-excel text-6xl text-green-500"></i>
              <div>
                <p class="text-lg font-medium text-gray-700">Pilih File Excel</p>
                <p class="text-sm text-gray-500">Format yang didukung: .xlsx, .xls (Max 10MB)</p>
              </div>
              <input
                ref="fileInput"
                type="file"
                @change="handleFileSelect"
                accept=".xlsx,.xls"
                class="hidden"
                required
              />
              <button
                type="button"
                @click="$refs.fileInput.click()"
                class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium transition-colors"
              >
                <i class="fa fa-folder-open mr-2"></i>Pilih File
              </button>
            </div>
          </div>

          <!-- Selected File Info -->
          <div v-if="selectedFile" class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <i class="fa-solid fa-file-excel text-green-500 text-xl"></i>
                <div>
                  <p class="font-medium text-green-800">{{ selectedFile.name }}</p>
                  <p class="text-sm text-green-600">{{ formatFileSize(selectedFile.size) }}</p>
                </div>
              </div>
              <button
                type="button"
                @click="clearFile"
                class="text-red-500 hover:text-red-700 transition-colors"
              >
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>

          <!-- Upload Button -->
          <div class="flex justify-end space-x-4">
            <button
              type="button"
              @click="downloadTemplate"
              class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors"
            >
              <i class="fa fa-download mr-2"></i>Download Template
            </button>
            <button
              type="submit"
              :disabled="!selectedFile || uploading"
              class="bg-green-500 hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-6 py-2 rounded-lg font-medium transition-colors flex items-center"
            >
              <i v-if="uploading" class="fa fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa fa-upload mr-2"></i>
              {{ uploading ? 'Memproses...' : 'Upload File' }}
            </button>
          </div>
        </form>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 mt-6">
          <h3 class="text-lg font-semibold text-blue-800 mb-4">
            <i class="fa fa-info-circle mr-2"></i>Petunjuk Upload
          </h3>
          <div class="space-y-3 text-blue-700">
            <div class="flex items-start space-x-3">
              <i class="fa fa-check-circle text-blue-500 mt-1"></i>
              <p>Download template Excel terlebih dahulu untuk memastikan format yang benar</p>
            </div>
            <div class="flex items-start space-x-3">
              <i class="fa fa-check-circle text-blue-500 mt-1"></i>
              <p>Kolom <strong>ID</strong>, <strong>NIK</strong>, dan <strong>Nama Lengkap</strong> wajib diisi</p>
            </div>
            <div class="flex items-start space-x-3">
              <i class="fa fa-check-circle text-blue-500 mt-1"></i>
              <p>Pastikan <strong>QR Code Outlet</strong>, <strong>Jabatan</strong>, dan <strong>Divisi</strong> sesuai dengan data yang ada di sistem</p>
            </div>
            <div class="flex items-start space-x-3">
              <i class="fa fa-check-circle text-blue-500 mt-1"></i>
              <p>Status hanya bisa diisi dengan <strong>A</strong> (Aktif) atau <strong>N</strong> (Non-Aktif)</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 