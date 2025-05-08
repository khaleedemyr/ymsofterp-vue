<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-2xl relative animate-fadeIn overflow-y-auto max-h-screen">
      <button @click="emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl">
        <i class="fas fa-times"></i>
      </button>
      <h2 class="text-xl font-bold mb-4 text-gray-700">{{ $t('create_task.title') }}</h2>
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">{{ $t('create_task.finding_problem') }}</label>
          <input v-model="form.title" type="text" class="w-full border rounded p-2" :placeholder="$t('create_task.finding_problem')" />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">{{ $t('create_task.description') }}</label>
          <textarea v-model="form.description" class="w-full border rounded p-2" :placeholder="$t('create_task.description')"></textarea>
        </div>
        <div class="mb-4 flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-semibold mb-1 flex items-center gap-2">
              {{ $t('create_task.categories') }}
              <button type="button" @click="showCategoryInfo" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-info-circle"></i>
              </button>
            </label>
            <select v-model="form.category" class="w-full border rounded p-2">
              <option value="">{{ $t('create_task.select_category') }}</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-sm font-semibold mb-1">{{ $t('create_task.priority') }}</label>
            <select v-model="form.priority" class="w-full border rounded p-2" @change="onPriorityChange">
              <option value="">{{ $t('create_task.select_priority') }}</option>
              <option v-for="prio in priorities" :key="prio.id" :value="prio.id">
                {{ prio.priority }}
              </option>
            </select>
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">{{ $t('create_task.due_date') }}</label>
          <input v-model="form.due_date" type="date" class="w-full border rounded p-2 bg-gray-100" readonly />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">{{ $t('kanban.capture') }}</label>
          <div class="flex gap-2 mb-2">
            <button type="button" class="px-4 py-2 rounded bg-violet-500 text-white flex items-center gap-2" @click="openPhotoCapture"><i class="fas fa-camera"></i> {{ $t('kanban.photo') }}</button>
            <button type="button" class="px-4 py-2 rounded bg-teal-500 text-white flex items-center gap-2" @click="openVideoCapture"><i class="fas fa-video"></i> {{ $t('kanban.video') }}</button>
          </div>
          <!-- Preview foto -->
          <div class="flex gap-2 flex-wrap mt-2">
            <div v-for="(img, i) in capturedPhotos" :key="i" class="relative group">
              <img :src="img" class="w-20 h-20 object-cover rounded shadow border" />
              <button @click="removeCapturedPhoto(i)" class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-trash text-white text-xl"></i>
              </button>
            </div>
          </div>
          <!-- Preview video -->
          <div class="flex gap-2 flex-wrap mt-2">
            <div v-for="(vid, i) in capturedVideos" :key="i" class="relative group">
              <video :src="vid.url" class="w-24 h-20 rounded shadow border" controls />
              <button @click="removeCapturedVideo(i)" class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-trash text-white text-xl"></i>
              </button>
            </div>
          </div>
          <!-- Hidden input untuk capture -->
          <input ref="photoInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPhotoCapture" multiple>
          <input ref="videoInput" type="file" accept="video/*" capture="environment" class="hidden" @change="onVideoCapture" multiple>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">{{ $t('kanban.upload_media') }}</label>
          <input type="file" class="w-full border rounded p-2" multiple accept="image/*,video/*" @change="onUploadMedia" />
          <div class="flex gap-2 flex-wrap mt-2">
            <div v-for="(file, i) in uploadedMedia" :key="i" class="relative group">
              <img v-if="file.type.startsWith('image/')" :src="file.url" class="w-20 h-20 object-cover rounded shadow border" />
              <video v-else-if="file.type.startsWith('video/')" :src="file.url" class="w-24 h-20 rounded shadow border" controls />
              <button @click="removeUploadedMedia(i)" class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-trash text-white text-xl"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">{{ $t('kanban.upload_doc') }}</label>
          <input type="file" class="w-full border rounded p-2" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv" @change="onUploadDocs" />
          <div class="flex gap-2 flex-wrap mt-2">
            <div v-for="(file, i) in uploadedDocs" :key="i" class="flex items-center gap-2 border rounded px-2 py-1 bg-gray-50">
              <i :class="['fas', getFileIcon(file).icon, getFileIcon(file).color, 'text-2xl']"></i>
              <span class="truncate max-w-[120px]">{{ file.name }}</span>
              <button @click="removeUploadedDoc(i)" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button type="button" @click="emit('close')" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300">{{ $t('create_task.cancel') }}</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">{{ $t('create_task.save') }}</button>
        </div>
      </form>
      <CameraModal v-if="isCameraModalOpen" :mode="cameraMode" @close="isCameraModalOpen = false" @capture="onCameraCapture" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import CameraModal from './CameraModal.vue';

// Konfigurasi axios
axios.defaults.withCredentials = true;

const props = defineProps({
  outlet: [String, Number],
  ruko: [String, Number]
});

const emit = defineEmits(['close', 'taskCreated']);

const { t } = useI18n();

const form = ref({
  title: '',
  description: '',
  category: '',
  priority: '',
  due_date: '',
  id_outlet: '',
  id_ruko: '',
});

const categories = ref([]);
const priorities = ref([]);
const capturedPhotos = ref([]);
const capturedVideos = ref([]);
const uploadedMedia = ref([]);
const uploadedDocs = ref([]);
const photoInput = ref(null);
const videoInput = ref(null);
const isCameraModalOpen = ref(false);
const cameraMode = ref('photo'); // 'photo' atau 'video'

onMounted(async () => {
  categories.value = (await axios.get('/api/maintenance-labels')).data;
  priorities.value = (await axios.get('/api/maintenance-priorities')).data;
  watch(() => props.outlet, (val) => {
    form.value.id_outlet = val;
  }, { immediate: true });
  watch(() => props.ruko, (val) => {
    form.value.id_ruko = val;
  }, { immediate: true });
});

async function onPriorityChange() {
  const prio = priorities.value.find(p => p.id == form.value.priority);
  if (!prio) return;
  const result = await Swal.fire({
    title: `${prio.priority} - ${prio.max_days} hari`,
    html: `<div class='mb-2'>${prio.description}</div><div class='text-xs text-gray-500 mt-2'>${t('create_task.wise_priority')}</div>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes',
    cancelButtonText: 'No',
  });
  if (result.isConfirmed) {
    // Set due date hari ini + max_days
    const today = new Date();
    today.setDate(today.getDate() + prio.max_days);
    form.value.due_date = today.toISOString().slice(0, 10);
  } else {
    form.value.priority = '';
    form.value.due_date = '';
  }
}

function mapStatus(status) {
  switch (status) {
    case 'ToDo': return 'TASK';
    case 'InProgress': return 'IN_PROGRESS';
    case 'InReview': return 'IN_REVIEW';
    case 'Done': return 'DONE';
    default: return status; // PR, PO, dll
  }
}

async function submitForm() {
  // Validasi outlet dan ruko
  if (!form.value.id_outlet) {
    Swal.fire('Error', 'Pilih outlet terlebih dahulu!', 'error');
    return;
  }
  if (form.value.id_outlet == 1 && !form.value.id_ruko) {
    Swal.fire('Error', 'Pilih ruko terlebih dahulu!', 'error');
    return;
  }

  // Validasi field wajib
  if (!form.value.title) {
    Swal.fire('Error', 'Judul task harus diisi!', 'error');
    return;
  }
  if (!form.value.category) {
    Swal.fire('Error', 'Kategori harus dipilih!', 'error');
    return;
  }
  if (!form.value.priority) {
    Swal.fire('Error', 'Prioritas harus dipilih!', 'error');
    return;
  }
  if (!form.value.due_date) {
    Swal.fire('Error', 'Due date harus diisi!', 'error');
    return;
  }

  try {
    const fd = new FormData();
    fd.append('title', form.value.title);
    fd.append('description', form.value.description);
    fd.append('category', form.value.category);
    fd.append('priority', form.value.priority);
    fd.append('due_date', form.value.due_date);
    fd.append('id_outlet', form.value.id_outlet || '');
    fd.append('id_ruko', form.value.id_ruko || '');
    fd.append('status', mapStatus('ToDo'));

    // Media (foto/video)
    capturedPhotos.value.forEach((img, i) => {
      const file = dataURLtoFile(img, `photo_${i}.png`);
      if (file) fd.append('media[]', file);
    });
    
    capturedVideos.value.forEach((item, i) => {
      if (item.blob instanceof Blob) {
        const file = new File([item.blob], `video_${i}.webm`, { type: 'video/webm' });
        fd.append('media[]', file);
      }
    });
    
    uploadedMedia.value.forEach((item) => {
      if (item.file instanceof File) {
        fd.append('media[]', item.file);
      }
    });
    
    // Dokumen
    uploadedDocs.value.forEach((file) => {
      fd.append('documents[]', file);
    });

    const response = await axios.post('/api/maintenance-order', fd, {
      headers: { 
        'Content-Type': 'multipart/form-data'
      },
      withCredentials: true
    });

    if (response.data.success) {
      resetForm();
      emit('taskCreated');
      emit('close');
      Swal.fire({
        title: 'Sukses',
        text: 'Task berhasil dibuat',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
      });
    } else {
      throw new Error(response.data.error || 'Gagal membuat task');
    }
  } catch (e) {
    console.error('Error creating task:', e);
    Swal.fire('Gagal', e.response?.data?.error || e.message || 'Gagal membuat task', 'error');
  }
}

function dataURLtoFile(dataurl, filename) {
  if (!dataurl || typeof dataurl !== 'string' || !dataurl.includes(',')) return null;
  var arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1], bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
  for (let i = 0; i < n; ++i) u8arr[i] = bstr.charCodeAt(i);
  return new File([u8arr], filename, { type: mime });
}
function resetForm() {
  form.value.title = '';
  form.value.description = '';
  form.value.category = '';
  form.value.priority = '';
  form.value.due_date = '';
  capturedPhotos.value = [];
  capturedVideos.value.forEach(item => {
    if (item.url) URL.revokeObjectURL(item.url);
  });
  capturedVideos.value = [];
  uploadedMedia.value = [];
  uploadedDocs.value = [];
}

// Helper untuk preview dokumen
function getFileIcon(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  if (['pdf'].includes(ext)) return { icon: 'fa-file-pdf', color: 'text-red-500' };
  if (['doc', 'docx'].includes(ext)) return { icon: 'fa-file-word', color: 'text-blue-500' };
  if (['xls', 'xlsx', 'csv'].includes(ext)) return { icon: 'fa-file-excel', color: 'text-green-500' };
  return { icon: 'fa-file', color: 'text-gray-400' };
}

function openPhotoCapture() {
  if (isMobile()) {
    photoInput.value && photoInput.value.click();
  } else {
    cameraMode.value = 'photo';
    isCameraModalOpen.value = true;
  }
}
function openVideoCapture() {
  if (isMobile()) {
    videoInput.value && videoInput.value.click();
  } else {
    cameraMode.value = 'video';
    isCameraModalOpen.value = true;
  }
}
function onPhotoCapture(e) {
  for (const file of e.target.files) {
    const reader = new FileReader();
    reader.onload = ev => capturedPhotos.value.push(ev.target.result);
    reader.readAsDataURL(file);
  }
  e.target.value = '';
}
function onVideoCapture(e) {
  for (const file of e.target.files) {
    const url = URL.createObjectURL(file);
    capturedVideos.value.push({ 
      blob: file, 
      url: url
    });
  }
  e.target.value = '';
}
function removeCapturedPhoto(i) {
  capturedPhotos.value.splice(i, 1);
}
function removeCapturedVideo(i) {
  if (capturedVideos.value[i]?.url) {
    URL.revokeObjectURL(capturedVideos.value[i].url);
  }
  capturedVideos.value.splice(i, 1);
}

function onUploadMedia(e) {
  for (const file of e.target.files) {
    const url = URL.createObjectURL(file);
    uploadedMedia.value.push({ url, type: file.type, name: file.name, file });
  }
  e.target.value = '';
}
function removeUploadedMedia(i) {
  uploadedMedia.value.splice(i, 1);
}

function onUploadDocs(e) {
  for (const file of e.target.files) {
    uploadedDocs.value.push(file);
  }
  e.target.value = '';
}
function removeUploadedDoc(i) {
  uploadedDocs.value.splice(i, 1);
}

function onCameraCapture(data) {
  if (cameraMode.value === 'photo') {
    capturedPhotos.value.push(data); // dataURL
  } else if (cameraMode.value === 'video') {
    capturedVideos.value.push({ 
      blob: data, 
      url: URL.createObjectURL(data)
    });
  }
  isCameraModalOpen.value = false;
}
function isMobile() {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function showCategoryInfo() {
  Swal.fire({
    title: 'Kategori Maintenance',
    html: `
      <div style='text-align:left'>
        <b>1. Mechanical</b><br>
        <span class='text-xs text-gray-500'>Berkaitan dengan sistem mekanis</span>
        <ul style='margin-left:1em; margin-bottom:0.5em'>
          <li>Genset</li>
          <li>Mesin Pompa Air</li>
          <li>Kitchen Exhaust Hood</li>
          <li>AC</li>
          <li>Dan lain-lain</li>
        </ul>
        <b>2. Electrical</b><br>
        <span class='text-xs text-gray-500'>Berkaitan dengan sistem kelistrikan dan elektronika</span>
        <ul style='margin-left:1em; margin-bottom:0.5em'>
          <li>Lampu</li>
          <li>Panel Listrik</li>
          <li>Stop Kontak</li>
          <li>Instalasi Kelistrikan</li>
          <li>Dan lain-lain</li>
        </ul>
        <b>3. Plumbing</b><br>
        <span class='text-xs text-gray-500'>Berkaitan dengan sistem perpipaan dalam suatu bangunan, mencakup instalasi saluran air bersih, air kotor, limbah, drainase, dan termasuk sistem gas</span>
        <ul style='margin-left:1em; margin-bottom:0.5em'>
          <li>Air Bersih / Kotor / IPAL</li>
          <li>Gas</li>
          <li>Toilet</li>
          <li>Water Toren</li>
          <li>Instalasi Pipa Air</li>
          <li>Dan lain-lain</li>
        </ul>
        <b>4. Machinery</b><br>
        <span class='text-xs text-gray-500'>Berkaitan dengan sistem pada penggunaan, pengoperasian, perawatan, dan pengelolaan mesin</span>
        <ul style='margin-left:1em; margin-bottom:0.5em'>
          <li>Grill</li>
          <li>Stove</li>
          <li>Fryer</li>
          <li>Chiller / Freezer</li>
          <li>Oven</li>
          <li>Microwave</li>
          <li>Warmer</li>
          <li>Dan lain-lain</li>
        </ul>
        <b>5. Civil</b><br>
        <span class='text-xs text-gray-500'>Berkaitan dengan sistem konstruksi sipil, mencakup struktur fisik seperti pondasi, rangka bangunan, jalan, dan infrastruktur lainnya.</span>
        <ul style='margin-left:1em; margin-bottom:0.5em'>
          <li>Furniture</li>
          <li>Infrastruktur Bangunan</li>
          <li>Interior / Exterior</li>
          <li>Dan lain-lain</li>
        </ul>
        <b>6. Others</b><br>
        <span class='text-xs text-gray-500'>Pekerjaan lainnya, diluar 5 kategori diatas</span>
      </div>
    `,
    width: 600,
    confirmButtonText: 'Tutup',
    customClass: {
      htmlContainer: 'text-left'
    }
  });
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
  animation: fadeIn 0.2s;
}
</style> 