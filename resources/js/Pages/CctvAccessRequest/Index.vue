<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  requests: Object,
  outlets: Array,
  filters: Object,
  isITManager: Boolean,
  isITTeam: Boolean,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const accessType = ref(props.filters?.access_type || 'all');
const perPage = ref(props.filters?.per_page || 15);
const showModal = ref(false);
const showDetailModal = ref(false);
const modalMode = ref('create');
const selectedRequest = ref(null);
const isLoading = ref(false);
const showUploadModal = ref(false);
const selectedUploadRequest = ref(null);
const playbackFiles = ref([]);
const isUploading = ref(false);
const validUntil = ref('');
const showPlaybackModal = ref(false);
const selectedPlaybackRequest = ref(null);
const selectedOutlets = ref([]);
const showOutletDropdown = ref(false);
const outletSearch = ref('');

const formData = ref({
  access_type: 'live_view',
  reason: '',
  outlet_ids: [],
  email: '',
  area: '',
  date_from: '',
  date_to: '',
  time_from: '',
  time_to: '',
  incident_description: '',
});

// Computed untuk filtered outlets
const filteredOutlets = computed(() => {
  if (!outletSearch.value) return props.outlets || [];
  const searchLower = outletSearch.value.toLowerCase();
  return (props.outlets || []).filter(outlet => 
    outlet.nama_outlet.toLowerCase().includes(searchLower)
  );
});

// Computed untuk selected outlet names
const selectedOutletNames = computed(() => {
  return selectedOutlets.value.map(id => {
    const outlet = props.outlets?.find(o => o.id_outlet === id);
    return outlet?.nama_outlet || id;
  });
});

function toggleOutlet(outletId) {
  const index = selectedOutlets.value.indexOf(outletId);
  if (index > -1) {
    selectedOutlets.value.splice(index, 1);
  } else {
    selectedOutlets.value.push(outletId);
  }
  formData.value.outlet_ids = [...selectedOutlets.value];
}

function removeOutlet(outletId) {
  const index = selectedOutlets.value.indexOf(outletId);
  if (index > -1) {
    selectedOutlets.value.splice(index, 1);
    formData.value.outlet_ids = [...selectedOutlets.value];
  }
}

const debouncedSearch = debounce(() => {
  router.get('/cctv-access-requests', {
    search: search.value,
    status: status.value,
    access_type: accessType.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (!url) return;
  
  let page = 1;
  try {
    let fullUrl = url;
    if (url.startsWith('/')) {
      fullUrl = window.location.origin + url;
    } else if (!url.startsWith('http')) {
      fullUrl = window.location.origin + '/' + url;
    }
    
    const urlObj = new URL(fullUrl);
    const pageParam = urlObj.searchParams.get('page');
    if (pageParam) {
      page = parseInt(pageParam);
    }
  } catch (e) {
    const match = url.match(/[?&]page=(\d+)/);
    if (match) {
      page = parseInt(match[1]);
    }
  }
  
  if (isNaN(page) || page < 1) {
    page = 1;
  }
  
  const params = {
    page: page,
    per_page: perPage.value || 15,
  };
  
  if (search.value) {
    params.search = search.value;
  }
  if (status.value && status.value !== 'all') {
    params.status = status.value;
  }
  if (accessType.value && accessType.value !== 'all') {
    params.access_type = accessType.value;
  }
  
  router.get('/cctv-access-requests', params, { 
    preserveState: false,
    preserveScroll: false,
    replace: true
  });
}

function goToPageByNumber(page) {
  if (!page || page < 1) return;
  
  const params = {
    page: page,
    per_page: perPage.value || 15,
  };
  
  if (search.value) {
    params.search = search.value;
  }
  if (status.value && status.value !== 'all') {
    params.status = status.value;
  }
  if (accessType.value && accessType.value !== 'all') {
    params.access_type = accessType.value;
  }
  
  router.get('/cctv-access-requests', params, { 
    preserveState: false,
    preserveScroll: false,
    replace: true
  });
}

function openCreate() {
  modalMode.value = 'create';
  selectedRequest.value = null;
  selectedOutlets.value = [];
  outletSearch.value = '';
  formData.value = {
    access_type: 'live_view',
    reason: '',
    outlet_ids: [],
    email: '',
    area: '',
    date_from: '',
    date_to: '',
    time_from: '',
    time_to: '',
    incident_description: '',
  };
  showModal.value = true;
}

function openEdit(request) {
  modalMode.value = 'edit';
  selectedRequest.value = request;
  // Ensure outlet_ids is an array
  const outletIds = Array.isArray(request.outlet_ids) ? request.outlet_ids : (request.outlet_ids ? [request.outlet_ids] : []);
  selectedOutlets.value = [...outletIds];
  outletSearch.value = '';
  formData.value = {
    access_type: request.access_type,
    reason: request.reason,
    outlet_ids: outletIds,
    email: request.email || '',
    area: request.area || '',
    date_from: request.date_from || '',
    date_to: request.date_to || '',
    time_from: request.time_from || '',
    time_to: request.time_to || '',
    incident_description: request.incident_description || '',
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedRequest.value = null;
  selectedOutlets.value = [];
  outletSearch.value = '';
  showOutletDropdown.value = false;
  formData.value = {
    access_type: 'live_view',
    reason: '',
    outlet_ids: [],
    email: '',
    area: '',
    date_from: '',
    date_to: '',
    time_from: '',
    time_to: '',
    incident_description: '',
  };
}

function openUploadPlayback(request) {
  selectedUploadRequest.value = request;
  playbackFiles.value = [];
  validUntil.value = '';
  showUploadModal.value = true;
}

function closeUploadModal() {
  showUploadModal.value = false;
  selectedUploadRequest.value = null;
  playbackFiles.value = [];
  validUntil.value = '';
}

function handleFileChange(event) {
  const files = Array.from(event.target.files);
  
  if (files.length === 0) return;

  // Check total files (existing + new)
  const existingCount = selectedUploadRequest.value?.playback_file_path 
    ? (Array.isArray(selectedUploadRequest.value.playback_file_path) 
        ? selectedUploadRequest.value.playback_file_path.length 
        : 1)
    : 0;
  
  if (existingCount + files.length > 5) {
    Swal.fire('Error', `Maksimal 5 file playback. Anda sudah memiliki ${existingCount} file.`, 'error');
    event.target.value = '';
    return;
  }

  const validFiles = [];
  const allowedTypes = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-flv', 'video/webm'];

  for (const file of files) {
    // Validate file type
    if (!allowedTypes.includes(file.type)) {
      Swal.fire('Error', `File "${file.name}" harus berupa video (mp4, avi, mov, wmv, flv, webm)`, 'error');
      event.target.value = '';
      return;
    }
    
    // Validate file size (100MB)
    if (file.size > 100 * 1024 * 1024) {
      Swal.fire('Error', `File "${file.name}" ukuran maksimal 100MB`, 'error');
      event.target.value = '';
      return;
    }
    
    validFiles.push(file);
  }

  playbackFiles.value = [...playbackFiles.value, ...validFiles];
  event.target.value = ''; // Reset input to allow selecting same files again
}

function removeFile(index) {
  playbackFiles.value.splice(index, 1);
}

function viewPlaybackFiles(request) {
  selectedPlaybackRequest.value = request;
  showPlaybackModal.value = true;
}

function closePlaybackModal() {
  showPlaybackModal.value = false;
  selectedPlaybackRequest.value = null;
}

async function uploadPlayback() {
  if (playbackFiles.value.length === 0) {
    Swal.fire('Error', 'Pilih minimal satu file playback', 'error');
    return;
  }

  // Check total files limit
  const existingFiles = getPlaybackFiles(selectedUploadRequest.value);
  if (existingFiles.length + playbackFiles.value.length > 5) {
    Swal.fire('Error', `Maksimal 5 file playback. Anda sudah memiliki ${existingFiles.length} file.`, 'error');
    return;
  }

  if (!validUntil.value) {
    Swal.fire('Error', 'Tanggal valid until harus diisi', 'error');
    return;
  }

  // Check if valid_until is after today
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validUntilDate = new Date(validUntil.value);
  validUntilDate.setHours(0, 0, 0, 0);

  if (validUntilDate <= today) {
    Swal.fire('Error', 'Tanggal valid until harus setelah hari ini', 'error');
    return;
  }

  isUploading.value = true;
  try {
    const formData = new FormData();
    
    // Append all files
    playbackFiles.value.forEach((file) => {
      formData.append('files[]', file);
    });
    
    formData.append('valid_until', validUntil.value);

    const response = await axios.post(`/api/cctv-access-requests/${selectedUploadRequest.value.id}/upload-playback`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data && response.data.success) {
      Swal.fire('Berhasil', response.data.message || 'File playback berhasil diupload', 'success');
      closeUploadModal();
      reload();
    } else {
      const errorMsg = response.data?.message || 'Gagal mengupload file playback';
      Swal.fire('Error', errorMsg, 'error');
    }
  } catch (error) {
    console.error('Error uploading playback file:', error);
    const errorMsg = error.response?.data?.message || error.message || 'Gagal mengupload file playback';
    Swal.fire('Error', errorMsg, 'error');
  } finally {
    isUploading.value = false;
  }
}

async function save() {
  // Validation
  if (!formData.value.outlet_ids || formData.value.outlet_ids.length === 0) {
    Swal.fire('Error', 'Pilih minimal satu outlet', 'error');
    return;
  }

  if (formData.value.access_type === 'live_view' && !formData.value.email) {
    Swal.fire('Error', 'Email harus diisi untuk Live View', 'error');
    return;
  }

  isLoading.value = true;
  try {
    let response;
    const data = {
      access_type: formData.value.access_type,
      reason: formData.value.reason,
      outlet_ids: formData.value.outlet_ids,
    };

    if (formData.value.access_type === 'live_view') {
      data.email = formData.value.email;
    } else {
      data.area = formData.value.area;
      data.date_from = formData.value.date_from;
      data.date_to = formData.value.date_to;
      data.time_from = formData.value.time_from;
      data.time_to = formData.value.time_to;
      data.incident_description = formData.value.incident_description;
    }

    if (modalMode.value === 'create') {
      response = await axios.post('/api/cctv-access-requests', data);
    } else {
      response = await axios.put(`/api/cctv-access-requests/${selectedRequest.value.id}`, data);
    }
    
    if (response.data?.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
      closeModal();
    }
  } catch (error) {
    let errorMessage = 'Gagal menyimpan data';
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      errorMessage = Object.values(errors).flat().join(', ');
    }
    Swal.fire('Error', errorMessage, 'error');
  } finally {
    isLoading.value = false;
  }
}

async function viewDetail(request) {
  try {
    const response = await axios.get(`/api/cctv-access-requests/${request.id}`);
    if (response.data?.success) {
      selectedRequest.value = response.data.data;
      showDetailModal.value = true;
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal mengambil detail request', 'error');
  }
}

async function cancelRequest(request) {
  const result = await Swal.fire({
    title: 'Batalkan Request?',
    text: 'Yakin ingin membatalkan request ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Batalkan!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/api/cctv-access-requests/${request.id}`);
    if (response.data?.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal membatalkan request', 'error');
  }
}


async function revokeAccess(request) {
  const { value: reason } = await Swal.fire({
    title: 'Cabut Akses',
    input: 'textarea',
    inputLabel: 'Alasan Pencabutan *',
    inputPlaceholder: 'Masukkan alasan pencabutan akses...',
    inputAttributes: {
      'aria-label': 'Masukkan alasan pencabutan'
    },
    showCancelButton: true,
    confirmButtonText: 'Cabut',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (!value) {
        return 'Alasan pencabutan harus diisi';
      }
    }
  });

  if (!reason) return;

  try {
    const response = await axios.post(`/api/cctv-access-requests/${request.id}/revoke`, {
      revocation_reason: reason
    });
    
    if (response.data?.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal mencabut akses', 'error');
  }
}

function getStatusBadge(status) {
  const badges = {
    pending: 'bg-yellow-100 text-yellow-800 border border-yellow-300',
    approved: 'bg-green-100 text-green-800 border border-green-300',
    rejected: 'bg-red-100 text-red-800 border border-red-300',
    revoked: 'bg-orange-100 text-orange-800 border border-orange-300',
  };
  return badges[status] || 'bg-gray-100 text-gray-800 border border-gray-300';
}

function getStatusText(status) {
  const texts = {
    pending: 'Menunggu Approval',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    revoked: 'Dicabut',
  };
  return texts[status] || status;
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatTime(time) {
  if (!time) return '-';
  return time.substring(0, 5); // Format HH:mm
}

function formatDateOnly(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function getPlaybackFiles(request) {
  if (!request || !request.playback_file_path) {
    return [];
  }
  
  // If it's already an array, return it
  if (Array.isArray(request.playback_file_path)) {
    return request.playback_file_path;
  }
  
  // If it's a string (old format), convert to array
  if (typeof request.playback_file_path === 'string') {
    return [request.playback_file_path];
  }
  
  return [];
}

function isPlaybackExpired(request) {
  if (!request || request.access_type !== 'playback') {
    return false;
  }
  
  const files = getPlaybackFiles(request);
  if (files.length === 0) {
    return false;
  }
  
  if (!request.valid_until) {
    return false; // No expiration date means always valid
  }
  
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validUntil = new Date(request.valid_until);
  validUntil.setHours(0, 0, 0, 0);
  
  return today > validUntil;
}

function getOutletName(outletId) {
  const outlet = props.outlets?.find(o => o.id_outlet === outletId);
  return outlet?.nama_outlet || outletId;
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

// Watch filters
watch([status, accessType, perPage], () => {
  const params = {
    page: 1,
  };
  
  if (search.value) {
    params.search = search.value;
  }
  if (status.value && status.value !== 'all') {
    params.status = status.value;
  }
  if (accessType.value && accessType.value !== 'all') {
    params.access_type = accessType.value;
  }
  params.per_page = perPage.value || 15;
  
  router.get('/cctv-access-requests', params, { 
    preserveState: true, 
    replace: true 
  });
});

// Close dropdown when clicking outside
function handleClickOutside(event) {
  if (showOutletDropdown.value && !event.target.closest('.outlet-dropdown-container')) {
    showOutletDropdown.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
          <i class="fa-solid fa-video text-blue-600"></i> 
          <span>CCTV Access Request</span>
        </h1>
        <button 
          @click="openCreate" 
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Request
        </button>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-search mr-2 text-blue-500"></i>Search
            </label>
            <input
              type="text"
              v-model="search"
              @input="onSearchInput"
              placeholder="Cari alasan atau nama user..."
              class="w-full px-4 py-2.5 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-filter mr-2 text-blue-500"></i>Status
            </label>
            <select
              v-model="status"
              class="w-full px-4 py-2.5 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            >
              <option value="all">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="revoked">Revoked</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-video mr-2 text-blue-500"></i>Jenis Akses
            </label>
            <select
              v-model="accessType"
              class="w-full px-4 py-2.5 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            >
              <option value="all">Semua</option>
              <option value="live_view">Live View</option>
              <option value="playback">Playback</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-list mr-2 text-blue-500"></i>Per Page
            </label>
            <select
              v-model="perPage"
              class="w-full px-4 py-2.5 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            >
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">User</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Jenis Akses</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Email/Area</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Alasan</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-4 text-center text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!requests || !requests.data || requests.data.length === 0">
                <td colspan="9" class="px-6 py-10 text-center text-gray-400">
                  <i class="fa-solid fa-inbox text-4xl mb-2 block"></i>
                  Tidak ada data request
                </td>
              </tr>
              <tr v-else v-for="(item, index) in requests.data" :key="item.id" class="hover:bg-blue-50 transition-all">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                  {{ (requests.current_page - 1) * requests.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 rounded-full overflow-hidden bg-blue-100 flex items-center justify-center border-2 border-blue-200">
                      <img 
                        v-if="item.user?.avatar || item.user?.avatar_path" 
                        :src="`/storage/${item.user.avatar || item.user.avatar_path}`" 
                        :alt="item.user?.nama_lengkap || 'User'"
                        class="w-full h-full object-cover"
                      />
                      <i v-else class="fa-solid fa-user text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-semibold text-gray-900">{{ item.user?.nama_lengkap || '-' }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <span :class="item.access_type === 'live_view' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-purple-100 text-purple-700 border border-purple-300'" 
                          class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm">
                      <i :class="item.access_type === 'live_view' ? 'fa-solid fa-eye mr-1' : 'fa-solid fa-play mr-1'"></i>
                      {{ item.access_type === 'live_view' ? 'Live View' : 'Playback' }}
                    </span>
                    <span v-if="item.access_type === 'playback' && getPlaybackFiles(item).length > 0" 
                          class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold border border-green-300"
                          title="File playback tersedia">
                      <i class="fa-solid fa-video mr-1"></i>
                      {{ getPlaybackFiles(item).length }} file
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div v-if="item.outlet_ids && Array.isArray(item.outlet_ids) && item.outlet_ids.length > 0" class="flex flex-wrap gap-1 max-w-xs">
                    <span v-for="outletId in item.outlet_ids.slice(0, 3)" :key="outletId" 
                          class="px-2 py-1 bg-gradient-to-r from-blue-50 to-blue-100 text-blue-800 rounded-lg text-xs font-semibold border border-blue-200">
                      {{ getOutletName(outletId) }}
                    </span>
                    <span v-if="item.outlet_ids.length > 3" 
                          class="px-2 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-semibold">
                      +{{ item.outlet_ids.length - 3 }} lagi
                    </span>
                  </div>
                  <span v-else class="text-gray-400 text-sm">-</span>
                </td>
                <td class="px-6 py-4">
                  <div v-if="item.access_type === 'live_view'">
                    <div class="text-xs text-gray-500 mb-1">Email:</div>
                    <div class="text-sm font-medium text-gray-900">
                      <i class="fa-solid fa-envelope mr-1 text-blue-500"></i>
                      {{ item.email || '-' }}
                    </div>
                  </div>
                  <div v-else>
                    <div class="text-xs text-gray-500 mb-1">Area:</div>
                    <div class="text-sm font-medium text-gray-900">
                      <i class="fa-solid fa-map-marker-alt mr-1 text-purple-500"></i>
                      {{ item.area || '-' }}
                    </div>
                    <div v-if="item.date_from && item.date_to" class="text-xs text-gray-500 mt-1">
                      <i class="fa-solid fa-calendar mr-1 text-purple-500"></i>
                      {{ formatDateOnly(item.date_from) }} - {{ formatDateOnly(item.date_to) }}
                    </div>
                    <div v-if="item.time_from && item.time_to" class="text-xs text-gray-500 mt-1">
                      <i class="fa-solid fa-clock mr-1 text-purple-500"></i>
                      {{ formatTime(item.time_from) }} - {{ formatTime(item.time_to) }}
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="max-w-xs">
                    <div class="text-sm text-gray-900 line-clamp-2" :title="item.reason">
                      {{ item.reason }}
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadge(item.status)" 
                        class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm">
                    {{ getStatusText(item.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  {{ formatDate(item.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="viewDetail(item)"
                      class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg text-xs font-semibold transition shadow-sm"
                      title="Detail"
                    >
                      <i class="fa-solid fa-eye mr-1"></i> Detail
                    </button>
                    <button
                      v-if="item.status === 'pending' && item.user_id === $page.props.auth?.user?.id"
                      @click="openEdit(item)"
                      class="inline-flex items-center px-3 py-1.5 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg text-xs font-semibold transition shadow-sm"
                      title="Edit"
                    >
                      <i class="fa-solid fa-edit mr-1"></i> Edit
                    </button>
                    <button
                      v-if="item.status === 'pending' && item.user_id === $page.props.auth?.user?.id"
                      @click="cancelRequest(item)"
                      class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg text-xs font-semibold transition shadow-sm"
                      title="Batalkan"
                    >
                      <i class="fa-solid fa-times mr-1"></i> Batal
                    </button>
                    <button
                      v-if="item.status === 'approved' && isITManager"
                      @click="revokeAccess(item)"
                      class="inline-flex items-center px-3 py-1.5 bg-orange-100 text-orange-700 hover:bg-orange-200 rounded-lg text-xs font-semibold transition shadow-sm"
                      title="Cabut Akses"
                    >
                      <i class="fa-solid fa-ban mr-1"></i> Revoke
                    </button>
                    <button
                      v-if="item.status === 'approved' && item.access_type === 'playback' && isITTeam"
                      @click="openUploadPlayback(item)"
                      class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded-lg text-xs font-semibold transition shadow-sm"
                      :title="getPlaybackFiles(item).length >= 5 ? 'Sudah mencapai maksimal 5 file' : 'Upload Playback'"
                      :disabled="getPlaybackFiles(item).length >= 5"
                    >
                      <i class="fa-solid fa-upload mr-1"></i> Upload Playback
                      <span v-if="getPlaybackFiles(item).length > 0" class="ml-1">({{ getPlaybackFiles(item).length }}/5)</span>
                    </button>
                    <button
                      v-if="item.access_type === 'playback' && getPlaybackFiles(item).length > 0 && (isITTeam || item.user_id === $page.props.auth?.user?.id)"
                      @click="viewPlaybackFiles(item)"
                      class="inline-flex items-center px-3 py-1.5 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg text-xs font-semibold transition shadow-sm"
                      title="Lihat Playback"
                    >
                      <i class="fa-solid fa-video mr-1"></i> Lihat Playback
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="requests && requests.last_page > 1" class="bg-white px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(requests?.prev_page_url)"
                :disabled="!requests?.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(requests?.next_page_url)"
                :disabled="!requests?.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ requests?.from || 0 }}</span>
                  sampai
                  <span class="font-medium">{{ requests?.to || 0 }}</span>
                  dari
                  <span class="font-medium">{{ requests?.total || 0 }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(requests?.prev_page_url)"
                    :disabled="!requests?.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span
                    v-for="page in Array.from({ length: requests?.last_page || 0 }, (_, i) => i + 1)"
                    :key="page"
                    @click="goToPageByNumber(page)"
                    :class="page === requests?.current_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium cursor-pointer"
                  >
                    {{ page }}
                  </span>
                  <button
                    @click="goToPage(requests?.next_page_url)"
                    :disabled="!requests?.next_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Create/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" @click.self="closeModal">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-xl font-bold flex items-center gap-2">
              <i class="fa-solid fa-video"></i>
              {{ modalMode === 'create' ? 'Buat Request CCTV Access' : 'Edit Request' }}
            </h3>
            <button @click="closeModal" class="text-white hover:text-gray-200 transition">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
          
          <div class="p-6">
            <form @submit.prevent="save" class="space-y-6">
              <!-- Jenis Akses -->
              <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                  <i class="fa-solid fa-video mr-2 text-blue-500"></i>Jenis Akses *
                </label>
                <select
                  v-model="formData.access_type"
                  required
                  :disabled="modalMode === 'edit'"
                  class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition font-medium"
                >
                  <option value="live_view">Live View</option>
                  <option value="playback">Playback</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">
                  <span v-if="formData.access_type === 'live_view'">Akses untuk melihat CCTV secara langsung</span>
                  <span v-else>Akses untuk melihat rekaman CCTV (Hanya untuk tim IT)</span>
                </p>
              </div>

              <!-- Outlet Selection -->
              <div class="outlet-dropdown-container">
                <label class="block text-sm font-bold text-gray-700 mb-2">
                  <i class="fa-solid fa-store mr-2 text-blue-500"></i>Pilih Outlet *
                </label>
                <div class="relative">
                  <div 
                    @click="showOutletDropdown = !showOutletDropdown"
                    class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition cursor-pointer bg-white flex items-center justify-between min-h-[48px]"
                  >
                    <div class="flex flex-wrap gap-2 flex-1">
                      <span v-if="selectedOutlets.length === 0" class="text-gray-400">Pilih outlet...</span>
                      <span 
                        v-for="outletId in selectedOutlets" 
                        :key="outletId"
                        class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 rounded-lg text-sm font-semibold"
                      >
                        {{ getOutletName(outletId) }}
                        <button 
                          type="button"
                          @click.stop="removeOutlet(outletId)"
                          class="text-blue-600 hover:text-blue-800"
                        >
                          <i class="fa-solid fa-times text-xs"></i>
                        </button>
                      </span>
                    </div>
                    <i :class="['fa-solid transition-transform', showOutletDropdown ? 'fa-chevron-up' : 'fa-chevron-down', 'text-gray-400']"></i>
                  </div>
                  
                  <div 
                    v-if="showOutletDropdown"
                    class="absolute z-50 w-full mt-2 bg-white border-2 border-blue-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto outlet-dropdown-container"
                  >
                    <div class="p-3 border-b border-gray-200 sticky top-0 bg-white">
                      <input
                        v-model="outletSearch"
                        type="text"
                        placeholder="Cari outlet..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
                        @click.stop
                      />
                    </div>
                    <div class="p-2">
                      <label 
                        v-for="outlet in filteredOutlets" 
                        :key="outlet.id_outlet"
                        class="flex items-center px-3 py-2 hover:bg-blue-50 rounded-lg cursor-pointer"
                        @click.stop
                      >
                        <input
                          type="checkbox"
                          :checked="selectedOutlets.includes(outlet.id_outlet)"
                          @change="toggleOutlet(outlet.id_outlet)"
                          class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3 w-4 h-4"
                        />
                        <span class="text-sm font-medium text-gray-700">{{ outlet.nama_outlet }}</span>
                      </label>
                      <div v-if="filteredOutlets.length === 0" class="px-3 py-2 text-sm text-gray-400 text-center">
                        Tidak ada outlet ditemukan
                      </div>
                    </div>
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                  <i class="fa-solid fa-info-circle mr-1"></i>
                  Pilih satu atau lebih outlet yang ingin diakses ({{ selectedOutlets.length }} dipilih)
                </p>
              </div>

              <!-- Live View Fields -->
              <div v-if="formData.access_type === 'live_view'" class="bg-blue-50 p-4 rounded-xl border-2 border-blue-200">
                <div>
                  <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fa-solid fa-envelope mr-2 text-blue-500"></i>Email *
                  </label>
                  <input
                    v-model="formData.email"
                    type="email"
                    required
                    class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                    placeholder="contoh@email.com"
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    Email yang akan digunakan untuk akses CCTV Live View
                  </p>
                </div>
              </div>

              <!-- Playback Fields -->
              <div v-if="formData.access_type === 'playback'" class="bg-purple-50 p-4 rounded-xl border-2 border-purple-200">
                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                      <i class="fa-solid fa-map-marker-alt mr-2 text-purple-500"></i>Area *
                    </label>
                    <input
                      v-model="formData.area"
                      type="text"
                      required
                      class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                      placeholder="Contoh: Parkir Belakang, Lobi Utama, dll"
                    />
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fa-solid fa-calendar mr-2 text-purple-500"></i>Tanggal Mulai *
                      </label>
                      <input
                        v-model="formData.date_from"
                        type="date"
                        required
                        class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fa-solid fa-calendar mr-2 text-purple-500"></i>Tanggal Selesai *
                      </label>
                      <input
                        v-model="formData.date_to"
                        type="date"
                        required
                        :min="formData.date_from"
                        class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                      />
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fa-solid fa-clock mr-2 text-purple-500"></i>Waktu Mulai *
                      </label>
                      <input
                        v-model="formData.time_from"
                        type="time"
                        required
                        class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fa-solid fa-clock mr-2 text-purple-500"></i>Waktu Selesai *
                      </label>
                      <input
                        v-model="formData.time_to"
                        type="time"
                        required
                        class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                      />
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                      <i class="fa-solid fa-file-alt mr-2 text-purple-500"></i>Deskripsi Kejadian *
                    </label>
                    <textarea
                      v-model="formData.incident_description"
                      required
                      rows="4"
                      class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition resize-none"
                      placeholder="Jelaskan kejadian yang ingin dilihat di CCTV..."
                    ></textarea>
                  </div>
                </div>
              </div>

              <!-- Alasan -->
              <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                  <i class="fa-solid fa-comment-dots mr-2 text-blue-500"></i>Alasan Permintaan *
                </label>
                <textarea
                  v-model="formData.reason"
                  required
                  rows="4"
                  class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition resize-none"
                  placeholder="Jelaskan alasan permintaan akses CCTV..."
                ></textarea>
              </div>
              
              <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button
                  type="button"
                  @click="closeModal"
                  :disabled="isLoading"
                  class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed font-semibold transition shadow-sm"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  :disabled="isLoading"
                  class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl hover:from-blue-600 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed font-semibold transition shadow-lg flex items-center gap-2"
                >
                  <i v-if="isLoading" class="fa-solid fa-spinner fa-spin"></i>
                  <i v-else class="fa-solid fa-save"></i>
                  <span>{{ isLoading ? 'Menyimpan...' : 'Simpan' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Detail Modal -->
      <div v-if="showDetailModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" @click.self="showDetailModal = false">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-xl font-bold flex items-center gap-2">
              <i class="fa-solid fa-info-circle"></i> Detail Request
            </h3>
            <button @click="showDetailModal = false" class="text-white hover:text-gray-200 transition">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
          
          <div class="p-6">
            <div v-if="selectedRequest" class="space-y-6">
              <!-- Header Info -->
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                  <label class="block text-xs font-semibold text-gray-500 mb-2">User</label>
                  <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 h-12 w-12 rounded-full overflow-hidden bg-blue-100 flex items-center justify-center border-2 border-blue-200">
                      <img 
                        v-if="selectedRequest.user?.avatar || selectedRequest.user?.avatar_path" 
                        :src="`/storage/${selectedRequest.user.avatar || selectedRequest.user.avatar_path}`" 
                        :alt="selectedRequest.user?.nama_lengkap || 'User'"
                        class="w-full h-full object-cover"
                      />
                      <i v-else class="fa-solid fa-user text-blue-600 text-xl"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-900">{{ selectedRequest.user?.nama_lengkap || '-' }}</p>
                  </div>
                </div>
                <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                  <label class="block text-xs font-semibold text-gray-500 mb-1">Jenis Akses</label>
                  <span :class="selectedRequest.access_type === 'live_view' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-purple-100 text-purple-700 border border-purple-300'" 
                        class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm inline-block">
                    {{ selectedRequest.access_type === 'live_view' ? 'Live View' : 'Playback' }}
                  </span>
                </div>
                <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                  <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
                  <span :class="getStatusBadge(selectedRequest.status)" 
                        class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm inline-block">
                    {{ getStatusText(selectedRequest.status) }}
                  </span>
                </div>
                <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                  <label class="block text-xs font-semibold text-gray-500 mb-1">Tanggal Request</label>
                  <p class="text-sm font-bold text-gray-900">{{ formatDate(selectedRequest.created_at) }}</p>
                </div>
              </div>

              <!-- Outlet -->
              <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <label class="block text-xs font-semibold text-gray-500 mb-2">Outlet yang Diminta</label>
                <div v-if="selectedRequest.outlet_ids && Array.isArray(selectedRequest.outlet_ids) && selectedRequest.outlet_ids.length > 0" class="flex flex-wrap gap-2">
                  <span 
                    v-for="outletId in selectedRequest.outlet_ids" 
                    :key="outletId"
                    class="px-3 py-1.5 bg-gradient-to-r from-blue-50 to-blue-100 text-blue-800 rounded-lg text-sm font-semibold border border-blue-200"
                  >
                    {{ getOutletName(outletId) }}
                  </span>
                </div>
                <span v-else class="text-gray-400 text-sm">-</span>
              </div>

              <!-- Live View Info -->
              <div v-if="selectedRequest.access_type === 'live_view'" class="bg-blue-50 p-4 rounded-xl border-2 border-blue-200">
                <label class="block text-xs font-semibold text-gray-500 mb-2">
                  <i class="fa-solid fa-envelope mr-2"></i>Email
                </label>
                <p class="text-sm font-bold text-gray-900">{{ selectedRequest.email || '-' }}</p>
              </div>

              <!-- Playback Info -->
              <div v-if="selectedRequest.access_type === 'playback'" class="bg-purple-50 p-4 rounded-xl border-2 border-purple-200 space-y-4">
                <div>
                  <label class="block text-xs font-semibold text-gray-500 mb-2">
                    <i class="fa-solid fa-map-marker-alt mr-2"></i>Area
                  </label>
                  <p class="text-sm font-bold text-gray-900">{{ selectedRequest.area || '-' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">
                      <i class="fa-solid fa-calendar mr-2"></i>Tanggal Mulai
                    </label>
                    <p class="text-sm font-bold text-gray-900">{{ formatDateOnly(selectedRequest.date_from) || '-' }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">
                      <i class="fa-solid fa-calendar mr-2"></i>Tanggal Selesai
                    </label>
                    <p class="text-sm font-bold text-gray-900">{{ formatDateOnly(selectedRequest.date_to) || '-' }}</p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">
                      <i class="fa-solid fa-calendar mr-2"></i>Tanggal Mulai
                    </label>
                    <p class="text-sm font-bold text-gray-900">{{ formatDateOnly(selectedRequest.date_from) || '-' }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">
                      <i class="fa-solid fa-calendar mr-2"></i>Tanggal Selesai
                    </label>
                    <p class="text-sm font-bold text-gray-900">{{ formatDateOnly(selectedRequest.date_to) || '-' }}</p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Waktu Mulai</label>
                    <p class="text-sm font-bold text-gray-900">{{ formatTime(selectedRequest.time_from) || '-' }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Waktu Selesai</label>
                    <p class="text-sm font-bold text-gray-900">{{ formatTime(selectedRequest.time_to) || '-' }}</p>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-500 mb-2">Deskripsi Kejadian</label>
                  <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ selectedRequest.incident_description || '-' }}</p>
                </div>
                <!-- Playback File -->
                <div v-if="getPlaybackFiles(selectedRequest).length > 0" class="bg-white p-4 rounded-lg border border-purple-300">
                  <label class="block text-xs font-semibold text-gray-500 mb-3">
                    <i class="fa-solid fa-file-video mr-2 text-purple-500"></i>File Playback ({{ getPlaybackFiles(selectedRequest).length }} file)
                  </label>
                  <div class="space-y-3">
                    <div v-if="selectedRequest.playback_uploaded_at" class="text-xs text-gray-500">
                      Diupload pada: {{ formatDate(selectedRequest.playback_uploaded_at) }}
                    </div>
                    <div v-if="selectedRequest.playback_uploaded_by" class="text-xs text-gray-500">
                      Oleh: {{ selectedRequest.playback_uploaded_by?.nama_lengkap || '-' }}
                    </div>
                    <div v-if="selectedRequest.valid_until" class="text-xs" :class="isPlaybackExpired(selectedRequest) ? 'text-red-600 font-semibold' : 'text-gray-500'">
                      Valid until: {{ formatDateOnly(selectedRequest.valid_until) }}
                      <span v-if="isPlaybackExpired(selectedRequest)" class="ml-2">
                        <i class="fa-solid fa-exclamation-triangle"></i> Expired
                      </span>
                    </div>
                    <div class="mt-3 space-y-3">
                      <div v-for="(filePath, index) in getPlaybackFiles(selectedRequest)" :key="index" 
                           class="bg-white rounded-lg border border-purple-200 overflow-hidden">
                        <div class="p-3 bg-purple-50 border-b border-purple-200">
                          <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                              <i class="fa-solid fa-video text-purple-500"></i>
                              <span class="text-sm font-semibold text-gray-900">File {{ index + 1 }}</span>
                            </div>
                            <span 
                              v-if="isPlaybackExpired(selectedRequest) && selectedRequest.user_id === $page.props.auth?.user?.id"
                              class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-semibold"
                            >
                              <i class="fa-solid fa-lock mr-1"></i> Expired
                            </span>
                          </div>
                        </div>
                        <div v-if="!isPlaybackExpired(selectedRequest) && (isITTeam || selectedRequest.user_id === $page.props.auth?.user?.id)" 
                             class="p-3">
                          <video 
                            :src="`/cctv-access-requests/${selectedRequest.id}/playback/${index}`" 
                            controls 
                            class="w-full rounded-lg shadow-sm"
                            preload="metadata"
                          >
                            Browser Anda tidak mendukung video player.
                          </video>
                        </div>
                        <div v-else-if="isPlaybackExpired(selectedRequest) && selectedRequest.user_id === $page.props.auth?.user?.id"
                             class="p-6 text-center">
                          <i class="fa-solid fa-lock text-2xl text-gray-400 mb-2"></i>
                          <p class="text-xs text-gray-500">File playback sudah expired</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Alasan -->
              <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <label class="block text-xs font-semibold text-gray-500 mb-2">Alasan Permintaan</label>
                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ selectedRequest.reason || '-' }}</p>
              </div>

              <!-- Approval Info -->
              <div v-if="selectedRequest.it_manager" class="bg-green-50 p-4 rounded-xl border border-green-200">
                <label class="block text-xs font-semibold text-gray-500 mb-2">IT Manager</label>
                <p class="text-sm font-bold text-gray-900">{{ selectedRequest.it_manager?.nama_lengkap || '-' }}</p>
                <p v-if="selectedRequest.approved_at" class="text-xs text-gray-500 mt-1">
                  Disetujui pada: {{ formatDate(selectedRequest.approved_at) }}
                </p>
                <p v-if="selectedRequest.rejected_at" class="text-xs text-gray-500 mt-1">
                  Ditolak pada: {{ formatDate(selectedRequest.rejected_at) }}
                </p>
              </div>

              <div v-if="selectedRequest.approval_notes" class="bg-yellow-50 p-4 rounded-xl border border-yellow-200">
                <label class="block text-xs font-semibold text-gray-500 mb-2">Catatan Approval</label>
                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ selectedRequest.approval_notes }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Playback Modal -->
    <div v-if="showUploadModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" @click.self="closeUploadModal">
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-purple-700 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
          <h2 class="text-xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-upload"></i>
            Upload File Playback
          </h2>
          <button @click="closeUploadModal" class="text-white hover:text-gray-200 transition">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div class="bg-purple-50 p-4 rounded-xl border-2 border-purple-200">
            <p class="text-sm text-gray-700 mb-2">
              <strong>Request ID:</strong> #{{ selectedUploadRequest?.id }}
            </p>
            <p class="text-sm text-gray-700">
              <strong>User:</strong> {{ selectedUploadRequest?.user?.nama_lengkap || '-' }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
              <i class="fa-solid fa-file-video mr-2 text-purple-500"></i>Pilih File Playback * (Maksimal 5 file)
            </label>
            <input
              type="file"
              @change="handleFileChange"
              accept="video/mp4,video/avi,video/quicktime,video/x-msvideo,video/x-ms-wmv,video/x-flv,video/webm"
              multiple
              class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
            />
            <p class="text-xs text-gray-500 mt-2">
              Format yang didukung: MP4, AVI, MOV, WMV, FLV, WEBM (Maksimal 100MB per file, maksimal 5 file)
            </p>
            
            <!-- Display selected files -->
            <div v-if="playbackFiles.length > 0" class="mt-3 space-y-2">
              <div v-for="(file, index) in playbackFiles" :key="index" 
                   class="flex items-center justify-between p-3 bg-purple-50 rounded-lg border border-purple-200">
                <div class="flex items-center gap-2 flex-1">
                  <i class="fa-solid fa-video text-purple-500"></i>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ file.name }}</p>
                    <p class="text-xs text-gray-500">{{ (file.size / 1024 / 1024).toFixed(2) }} MB</p>
                  </div>
                </div>
                <button
                  @click="removeFile(index)"
                  class="ml-2 p-1.5 text-red-600 hover:bg-red-100 rounded-lg transition"
                  title="Hapus file"
                >
                  <i class="fa-solid fa-times"></i>
                </button>
              </div>
              <p class="text-xs text-purple-700 font-semibold">
                Total: {{ playbackFiles.length }} file dipilih
              </p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
              <i class="fa-solid fa-calendar-times mr-2 text-purple-500"></i>Valid Until (Berlaku Sampai) *
            </label>
            <input
              v-model="validUntil"
              type="date"
              required
              :min="new Date().toISOString().split('T')[0]"
              class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
            />
            <p class="text-xs text-gray-500 mt-2">
              Setelah tanggal ini, user pemohon tidak dapat melihat atau mengunduh file playback
            </p>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button
              @click="closeUploadModal"
              class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-semibold"
            >
              Batal
            </button>
            <button
              @click="uploadPlayback"
              :disabled="playbackFiles.length === 0 || isUploading"
              class="px-6 py-2 bg-gradient-to-r from-purple-500 to-purple-700 text-white rounded-xl hover:from-purple-600 hover:to-purple-800 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="isUploading" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-upload"></i>
              {{ isUploading ? 'Mengupload...' : `Upload (${playbackFiles.length} file)` }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Playback Files Modal -->
    <div v-if="showPlaybackModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" @click.self="closePlaybackModal">
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-green-500 to-green-700 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
          <h2 class="text-xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-video"></i>
            File Playback
          </h2>
          <button @click="closePlaybackModal" class="text-white hover:text-gray-200 transition">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>

        <div class="p-6">
          <div v-if="selectedPlaybackRequest" class="space-y-4">
            <!-- Request Info -->
            <div class="bg-green-50 p-4 rounded-xl border-2 border-green-200">
              <p class="text-sm text-gray-700 mb-2">
                <strong>Request ID:</strong> #{{ selectedPlaybackRequest.id }}
              </p>
              <p class="text-sm text-gray-700 mb-2">
                <strong>User:</strong> {{ selectedPlaybackRequest.user?.nama_lengkap || '-' }}
              </p>
              <p v-if="selectedPlaybackRequest.playback_uploaded_at" class="text-sm text-gray-700 mb-2">
                <strong>Diupload pada:</strong> {{ formatDate(selectedPlaybackRequest.playback_uploaded_at) }}
              </p>
              <p v-if="selectedPlaybackRequest.playback_uploaded_by" class="text-sm text-gray-700 mb-2">
                <strong>Oleh:</strong> {{ selectedPlaybackRequest.playback_uploaded_by?.nama_lengkap || '-' }}
              </p>
              <p v-if="selectedPlaybackRequest.valid_until" class="text-sm" :class="isPlaybackExpired(selectedPlaybackRequest) ? 'text-red-600 font-semibold' : 'text-gray-700'">
                <strong>Valid until:</strong> {{ formatDateOnly(selectedPlaybackRequest.valid_until) }}
                <span v-if="isPlaybackExpired(selectedPlaybackRequest)" class="ml-2">
                  <i class="fa-solid fa-exclamation-triangle"></i> Expired
                </span>
              </p>
            </div>

            <!-- Files List -->
            <div class="space-y-4">
              <h3 class="text-lg font-bold text-gray-900">
                <i class="fa-solid fa-file-video mr-2 text-green-500"></i>
                Daftar File ({{ getPlaybackFiles(selectedPlaybackRequest).length }} file)
              </h3>
              <div v-for="(filePath, index) in getPlaybackFiles(selectedPlaybackRequest)" :key="index" 
                   class="bg-white rounded-xl border-2 border-green-200 overflow-hidden">
                <div class="p-4 bg-green-50 border-b border-green-200">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-video text-green-600"></i>
                      </div>
                      <div>
                        <p class="text-sm font-bold text-gray-900">File {{ index + 1 }}</p>
                        <p class="text-xs text-gray-500">{{ filePath.split('/').pop() }}</p>
                      </div>
                    </div>
                    <span 
                      v-if="isPlaybackExpired(selectedPlaybackRequest) && selectedPlaybackRequest.user_id === $page.props.auth?.user?.id"
                      class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold"
                    >
                      <i class="fa-solid fa-lock mr-1"></i> Expired
                    </span>
                  </div>
                </div>
                <div v-if="!isPlaybackExpired(selectedPlaybackRequest) && (isITTeam || selectedPlaybackRequest.user_id === $page.props.auth?.user?.id)" 
                     class="p-4">
                  <video 
                    :src="`/cctv-access-requests/${selectedPlaybackRequest.id}/playback/${index}`" 
                    controls 
                    class="w-full rounded-lg shadow-lg"
                    preload="metadata"
                  >
                    Browser Anda tidak mendukung video player.
                  </video>
                </div>
                <div v-else-if="isPlaybackExpired(selectedPlaybackRequest) && selectedPlaybackRequest.user_id === $page.props.auth?.user?.id"
                     class="p-8 text-center">
                  <i class="fa-solid fa-lock text-4xl text-gray-400 mb-3"></i>
                  <p class="text-gray-500 font-semibold">File playback sudah expired</p>
                  <p class="text-sm text-gray-400 mt-1">Valid until: {{ formatDateOnly(selectedPlaybackRequest.valid_until) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
