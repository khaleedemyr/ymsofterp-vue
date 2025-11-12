<template>
  <AppLayout title="Buat Push Notification Baru">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bell text-purple-500"></i> Buat Push Notification Baru
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <!-- Error Messages -->
      <div v-if="Object.keys(errors).length > 0" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
        <ul class="list-disc list-inside">
          <li v-for="(error, key) in errors" :key="key">{{ error }}</li>
        </ul>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Target Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Target Email Member <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.txt_target"
              type="text"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              :class="{ 'border-red-500': errors.txt_target }"
              placeholder="Masukkan email (pisahkan dengan koma) atau ketik 'all' untuk semua devices"
              required
            />
            <p class="mt-1 text-sm text-gray-500">
              Tambahkan koma untuk multi target, ketik "all" untuk blast ke semua Devices.
            </p>
            <p v-if="errors.txt_target" class="text-red-500 text-sm mt-1">{{ errors.txt_target }}</p>
          </div>

          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Title <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.txt_title"
              type="text"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              :class="{ 'border-red-500': errors.txt_title }"
              placeholder="Masukkan title notifikasi"
              required
            />
            <p v-if="errors.txt_title" class="text-red-500 text-sm mt-1">{{ errors.txt_title }}</p>
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Body <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="form.txt_body"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              :class="{ 'border-red-500': errors.txt_body }"
              rows="4"
              placeholder="Masukkan isi notifikasi"
              required
            ></textarea>
            <p v-if="errors.txt_body" class="text-red-500 text-sm mt-1">{{ errors.txt_body }}</p>
          </div>

          <!-- Photo Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Foto (Opsional)
            </label>
            <input
              @change="handleFileChange"
              type="file"
              accept="image/*"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
            />
            <p class="mt-1 text-sm text-gray-500">
              Best Resolution 1024x500 px (Max: 2MB)
            </p>
            <div v-if="previewImage" class="mt-4">
              <img :src="previewImage" alt="Preview" class="max-w-xs rounded-lg shadow" />
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex justify-end gap-4 pt-6 border-t">
            <button
              type="button"
              @click="goBack"
              class="px-6 py-2 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-6 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition disabled:opacity-50"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa fa-paper-plane mr-2"></i>
              {{ loading ? 'Menyimpan...' : 'Simpan & Kirim' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  errors: Object,
});

const loading = ref(false);
const previewImage = ref(null);

const form = useForm({
  txt_target: '',
  txt_title: '',
  txt_body: '',
  file_foto: null,
});

function handleFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire('Error', 'Ukuran file maksimal 2MB', 'error');
      event.target.value = '';
      return;
    }

    // Validate file type
    if (!file.type.startsWith('image/')) {
      Swal.fire('Error', 'File harus berupa gambar', 'error');
      event.target.value = '';
      return;
    }

    form.file_foto = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImage.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function submit() {
  loading.value = true;
  
  form.post('/push-notification', {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire('Berhasil', 'Push notification berhasil dibuat!', 'success');
      router.visit('/push-notification');
    },
    onError: (errors) => {
      console.error('Error:', errors);
      Swal.fire('Error', 'Gagal membuat push notification', 'error');
    },
    onFinish: () => {
      loading.value = false;
    },
  });
}

function goBack() {
  router.visit('/push-notification');
}
</script>


