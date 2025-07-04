<template>
  <AppLayout>
    <div class="py-8 px-2 md:px-8 lg:px-16">
      <h1 class="text-2xl font-bold text-blue-700 mb-6">{{ isEdit ? 'Edit Checklist' : 'Tambah Checklist' }}</h1>
      <form @submit.prevent="submitForm" enctype="multipart/form-data" class="space-y-5 bg-white rounded-2xl shadow-xl p-4 md:p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Outlet</label>
            <select v-model="form.outlet_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id || outlet.id_outlet" :value="outlet.id || outlet.id_outlet">{{ outlet.name || outlet.nama_outlet }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal Kunjungan</label>
            <input type="date" v-model="form.visit_date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">User Input</label>
            <input type="text" :value="userNamaLengkap" class="mt-1 block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-700" readonly />
          </div>
        </div>
        <div class="overflow-x-auto w-full">
          <table class="table-auto w-full min-w-[900px] border mb-4">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="border px-2 py-1">No</th>
                <th class="border px-2 py-1">Kategori</th>
                <th class="border px-2 py-1">Checklist Point</th>
                <th class="border px-2 py-1">Check</th>
                <th class="border px-2 py-1">Actual Condition</th>
                <th class="border px-2 py-1">Action</th>
                <th class="border px-2 py-1">Picture</th>
                <th class="border px-2 py-1">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in form.items" :key="i">
                <td class="border px-2 py-1">{{ item.no }}</td>
                <td v-if="isFirstOfCategory(i)" :rowspan="categoryRowspan(item.category)" class="border px-2 py-1 align-top">{{ item.category }}</td>
                <td v-else style="display:none"></td>
                <td class="border px-2 py-1">{{ item.checklist_point }}</td>
                <td class="border px-2 py-1 text-center">
                  <input type="checkbox" v-model="item.checked" />
                </td>
                <td class="border px-2 py-1">
                  <input v-model="item.actual_condition" class="border rounded-lg px-2 py-1 w-full focus:ring-blue-500 focus:border-blue-500" />
                </td>
                <td class="border px-2 py-1">
                  <input v-model="item.action" class="border rounded-lg px-2 py-1 w-full focus:ring-blue-500 focus:border-blue-500" />
                </td>
                <td class="border px-2 py-1">
                  <div class="flex flex-col gap-1">
                    <input type="file" multiple accept="image/*" :capture="isMobile ? 'environment' : undefined" @change="onPhotoChange($event, i)" />
                    <button type="button" class="bg-blue-500 text-white px-2 py-1 rounded mb-1" @click="openCamera(i)">Ambil Foto</button>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <div v-for="(photo, idx) in item.photos" :key="idx" class="relative group">
                        <img :src="photoUrl(photo)" class="w-12 h-12 object-cover border rounded" />
                        <button type="button"
                          class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs opacity-80 group-hover:opacity-100"
                          @click="deletePhoto(i, idx)">
                          &times;
                        </button>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="border px-2 py-1">
                  <input v-model="item.remarks" class="border rounded-lg px-2 py-1 w-full focus:ring-blue-500 focus:border-blue-500" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex gap-2 justify-end mt-8">
          <button type="button" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200" @click="goBack">Batal</button>
          <button type="submit" class="px-6 py-2 rounded bg-blue-600 text-white font-bold hover:bg-blue-700 flex items-center justify-center min-w-[100px]" :disabled="loading">
            <span v-if="loading"><i class="fa fa-spinner fa-spin mr-2"></i>Loading...</span>
            <span v-else>Simpan</span>
          </button>
        </div>
      </form>
      <!-- Modal Kamera -->
      <div v-if="showCameraModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
        <div class="bg-white p-4 rounded shadow-lg relative flex flex-col items-center">
          <video ref="videoRef" autoplay class="w-80 h-60 bg-black rounded"></video>
          <canvas ref="canvasRef" class="hidden"></canvas>
          <div class="flex gap-2 mt-2">
            <button @click="capturePhoto" class="bg-green-500 text-white px-4 py-2 rounded">Capture</button>
            <button @click="switchCamera" class="bg-yellow-500 text-white px-4 py-2 rounded">Switch Camera</button>
            <button @click="closeCamera" class="bg-gray-300 px-4 py-2 rounded">Batal</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const page = usePage();
const isEdit = computed(() => page.props.isEdit);
const outlets = ref(page.props.outlets || []);
const template = ref(page.props.template || []);
const checklist = ref(page.props.checklist || null);
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

const form = ref({
  outlet_id: checklist.value?.outlet_id || '',
  visit_date: checklist.value?.visit_date || '',
  items: checklist.value?.items?.map(item => ({
    ...item,
    checked: !!item.checked,
    photos: item.photos ? item.photos.map(p => p.photo_path) : [],
  })) || template.value.map(([no, category, checklist_point]) => ({
    no, category, checklist_point, checked: false, actual_condition: '', action: '', remarks: '', photos: []
  }))
});

// Kamera
const showCameraModal = ref(false);
const currentItemIndex = ref(null);
const videoRef = ref(null);
const canvasRef = ref(null);
const cameraStream = ref(null);
const cameraFacingMode = ref('environment');
const loading = ref(false);

function openCamera(idx) {
  currentItemIndex.value = idx;
  showCameraModal.value = true;
  startCamera();
}
function startCamera() {
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(track => track.stop());
    cameraStream.value = null;
  }
  navigator.mediaDevices.getUserMedia({
    video: { facingMode: cameraFacingMode.value }
  }).then(stream => {
    cameraStream.value = stream;
    videoRef.value.srcObject = stream;
    videoRef.value.play();
  });
}
function switchCamera() {
  cameraFacingMode.value = cameraFacingMode.value === 'environment' ? 'user' : 'environment';
  startCamera();
}
function capturePhoto() {
  const video = videoRef.value;
  const canvas = canvasRef.value;
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);
  canvas.toBlob(blob => {
    form.value.items[currentItemIndex.value].photos.push(new File([blob], `photo_${Date.now()}.png`, { type: 'image/png' }));
    closeCamera();
  });
}
function closeCamera() {
  showCameraModal.value = false;
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(track => track.stop());
    cameraStream.value = null;
  }
}
function deletePhoto(itemIdx, photoIdx) {
  form.value.items[itemIdx].photos.splice(photoIdx, 1);
}

function onPhotoChange(e, idx) {
  const files = Array.from(e.target.files);
  if (!form.value.items[idx].photos) form.value.items[idx].photos = [];
  files.forEach(file => {
    form.value.items[idx].photos.push(file);
  });
}
function photoUrl(photo) {
  if (typeof photo === 'string') {
    // Sudah tersimpan di server
    return `/storage/${photo}`;
  }
  return URL.createObjectURL(photo);
}
function isFirstOfCategory(idx) {
  if (idx === 0) return true;
  return form.value.items[idx].category !== form.value.items[idx - 1].category;
}
function categoryRowspan(category) {
  return form.value.items.filter(i => i.category === category).length;
}
async function submitForm() {
  const confirm = await Swal.fire({
    title: 'Simpan Checklist?',
    text: 'Apakah Anda yakin ingin menyimpan data checklist ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal'
  });
  if (!confirm.isConfirmed) return;
  loading.value = true;
  const data = new FormData();
  data.append('outlet_id', form.value.outlet_id);
  data.append('visit_date', form.value.visit_date);
  form.value.items.forEach((item, i) => {
    data.append(`items[${i}][no]`, item.no);
    data.append(`items[${i}][category]`, item.category);
    data.append(`items[${i}][checklist_point]`, item.checklist_point);
    data.append(`items[${i}][checked]`, item.checked ? 1 : 0);
    data.append(`items[${i}][actual_condition]`, item.actual_condition || '');
    data.append(`items[${i}][action]`, item.action || '');
    data.append(`items[${i}][remarks]`, item.remarks || '');
    if (item.photos && item.photos.length) {
      item.photos.forEach((photo, j) => {
        if (typeof photo !== 'string') {
          data.append(`items[${i}][photos][${j}]`, photo);
        }
      });
    }
  });
  const inertiaOptions = {
    forceFormData: true,
    onError: () => {
      loading.value = false;
      Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data.', 'error');
    },
    onFinish: () => {
      loading.value = false;
    }
  };
  if (isEdit.value) {
    router.post(route('marketing-visit-checklist.update', checklist.value.id), data, inertiaOptions);
  } else {
    router.post(route('marketing-visit-checklist.store'), data, inertiaOptions);
  }
}
function goBack() {
  router.get(route('marketing-visit-checklist.index'));
}

const userNamaLengkap = computed(() => {
  if (checklist.value && checklist.value.user) {
    return checklist.value.user.nama_lengkap || checklist.value.user.name || '-';
  }
  if (page.props.auth && page.props.auth.user) {
    return page.props.auth.user.nama_lengkap || page.props.auth.user.name || '-';
  }
  return '-';
});
</script> 