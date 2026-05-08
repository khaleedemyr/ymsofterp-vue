<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  qris_image_path: { type: String, default: null },
  qris_image_url: { type: String, default: null },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);
const qrisImage = ref(null);
const removeQris = ref(false);

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonText: 'OK' });
  }
  if (flash.error) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error, confirmButtonText: 'OK' });
  }
});

function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  if (qrisImage.value) {
    fd.append('qris_image', qrisImage.value);
  }
  fd.append('remove_qris', removeQris.value ? '1' : '0');

  router.post('/web-profile/payment-settings/qris', fd, {
    forceFormData: true,
    onError: (e) => {
      errors.value = e || {};
      isSubmitting.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}
</script>

<template>
  <AppLayout title="Web Profile - Payment QRIS">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan Payment QRIS</h1>

      <form class="bg-white rounded-lg shadow p-6 space-y-6" @submit.prevent="submit">
        <div class="space-y-2">
          <h2 class="text-lg font-semibold text-gray-800">QRIS Reservasi</h2>
          <p class="text-sm text-gray-600">
            File disimpan ke private storage (storage/app/private), bukan public storage.
            Frontend mengakses gambar melalui endpoint backend yang terkontrol.
          </p>
        </div>

        <div v-if="qris_image_url" class="space-y-2">
          <p class="text-sm font-medium text-gray-700">Preview QRIS Saat Ini</p>
          <img :src="qris_image_url" alt="QRIS Reservasi" class="max-h-80 rounded border border-gray-200 object-contain bg-gray-50" />
        </div>

        <div>
          <InputLabel value="Upload Gambar QRIS" />
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="mt-1 block w-full text-sm"
            @change="(e) => { qrisImage = e.target.files?.[0] || null; removeQris = false; }"
          />
          <p class="mt-2 text-xs text-gray-500">Format: JPG, PNG, WEBP. Maksimal 5 MB.</p>
          <InputError class="mt-1" :message="errors.qris_image" />
        </div>

        <label v-if="qris_image_path" class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="removeQris" type="checkbox" />
          Hapus gambar QRIS saat ini
        </label>

        <div class="flex justify-end">
          <PrimaryButton :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Pengaturan QRIS' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
