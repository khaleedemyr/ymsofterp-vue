<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <h2 class="text-xl font-bold mb-4 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-briefcase"></i> {{ form.id ? 'Edit' : 'Tambah' }} Lowongan
      </h2>
      <form @submit.prevent="submitForm" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="block font-semibold mb-1">Posisi/Jabatan</label>
          <input v-model="form.position" required class="w-full border rounded px-3 py-2" :disabled="isLoading" />
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Deskripsi Pekerjaan</label>
          <textarea v-model="form.description" required class="w-full border rounded px-3 py-2" rows="3" :disabled="isLoading"></textarea>
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Kualifikasi/Persyaratan</label>
          <textarea v-model="form.requirements" class="w-full border rounded px-3 py-2" rows="2" :disabled="isLoading"></textarea>
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Lokasi Penempatan</label>
          <input v-model="form.location" required class="w-full border rounded px-3 py-2" :disabled="isLoading" />
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Tanggal Penutupan</label>
          <input type="date" v-model="form.closing_date" required class="w-full border rounded px-3 py-2" :disabled="isLoading" />
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Status</label>
          <select v-model="form.is_active" class="w-full border rounded px-3 py-2" :disabled="isLoading">
            <option :value="1">Aktif</option>
            <option :value="0">Nonaktif</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Banner/Logo</label>
          <input type="file" @change="onFileChange" accept="image/*" class="w-full" :disabled="isLoading" />
          <div v-if="previewBanner" class="mt-2">
            <img :src="previewBanner" class="w-40 h-24 object-cover rounded shadow" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300" :disabled="isLoading">Batal</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 flex items-center gap-2" :disabled="isLoading">
            <span v-if="isLoading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
            {{ isLoading ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({ job: Object });
const emit = defineEmits(['close', 'saved']);

const form = ref({
  id: null,
  position: '',
  description: '',
  requirements: '',
  location: '',
  closing_date: '',
  is_active: 1,
  banner: null,
});
const previewBanner = ref('');
const isLoading = ref(false);

watch(() => props.job, (val) => {
  if (val) {
    form.value = {
      id: val.id,
      position: val.position,
      description: val.description,
      requirements: val.requirements,
      location: val.location,
      closing_date: val.closing_date,
      is_active: val.is_active,
      banner: null,
    };
    previewBanner.value = val.banner ? `/storage/${val.banner}` : '';
  } else {
    form.value = {
      id: null,
      position: '',
      description: '',
      requirements: '',
      location: '',
      closing_date: '',
      is_active: 1,
      banner: null,
    };
    previewBanner.value = '';
  }
}, { immediate: true });

function onFileChange(e) {
  const file = e.target.files[0];
  form.value.banner = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = e => previewBanner.value = e.target.result;
    reader.readAsDataURL(file);
  } else {
    previewBanner.value = '';
  }
}

async function submitForm() {
  try {
    isLoading.value = true;
    
    const data = new FormData();
    data.append('position', form.value.position);
    data.append('description', form.value.description);
    data.append('requirements', form.value.requirements);
    data.append('location', form.value.location);
    data.append('closing_date', form.value.closing_date);
    data.append('is_active', form.value.is_active);
    if (form.value.banner) data.append('banner', form.value.banner);
    
    let url = '/admin/job-vacancy';
    let method = 'post';
    if (form.value.id) {
      url += '/' + form.value.id;
      method = 'put';
    }
    
    await axios({ method, url, data, headers: { 'Content-Type': 'multipart/form-data' } });
    
    // Show success message
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      text: form.value.id ? 'Lowongan berhasil diperbarui!' : 'Lowongan berhasil ditambahkan!',
      timer: 2000,
      showConfirmButton: false
    });
    
    emit('saved');
    emit('close');
    
  } catch (error) {
    console.error('Error saving job vacancy:', error);
    
    // Show error message
    await Swal.fire({
      icon: 'error',
      title: 'Gagal!',
      text: error.response?.data?.message || 'Terjadi kesalahan saat menyimpan lowongan.',
      confirmButtonText: 'OK'
    });
    
  } finally {
    isLoading.value = false;
  }
}
</script> 