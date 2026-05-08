<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  mode: { type: String, default: 'create' },
  outlets: { type: Array, default: () => [] },
  selected_outlet_id: { type: Number, default: 0 },
  active_qris_url: { type: String, default: null },
  active_qris_hash: { type: String, default: null },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);
const qrisImage = ref(null);
const removeQris = ref(false);
const selectedOutletId = ref(props.selected_outlet_id || 0);

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success });
  if (flash.error) Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error });
});

function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  if (selectedOutletId.value) fd.append('outlet_id', String(selectedOutletId.value));
  if (qrisImage.value) fd.append('qris_image', qrisImage.value);
  fd.append('remove_qris', removeQris.value ? '1' : '0');

  router.post('/web-profile/payment-settings', fd, {
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
  <AppLayout title="Web Profile - Payment QRIS Form">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">
        {{ mode === 'edit' ? 'Edit QRIS' : 'Create QRIS' }}
      </h1>

      <form class="bg-white rounded-lg shadow p-6 space-y-6" @submit.prevent="submit">
        <div>
          <InputLabel value="Outlet" />
          <select v-model.number="selectedOutletId" class="mt-1 block w-full rounded border-gray-300">
            <option :value="0">Default (Semua Outlet)</option>
            <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
              {{ outlet.nama_outlet }}
            </option>
          </select>
        </div>

        <div v-if="active_qris_url" class="space-y-2">
          <p class="text-sm font-medium text-gray-700">QRIS aktif saat ini</p>
          <img :src="active_qris_url" alt="QRIS aktif" class="max-h-80 rounded border border-gray-200 object-contain bg-gray-50" />
          <p class="text-xs text-gray-500 break-all">{{ active_qris_hash || '-' }}</p>
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

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="removeQris" type="checkbox" />
          Ajukan hapus QRIS aktif
        </label>

        <div class="flex justify-between">
          <button type="button" class="px-4 py-2 rounded border" @click="router.visit('/web-profile/payment-settings')">Kembali</button>
          <PrimaryButton :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan (Pending Approval)' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
