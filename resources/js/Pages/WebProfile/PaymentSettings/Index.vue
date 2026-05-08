<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  selected_outlet_id: { type: Number, default: 0 },
  qris_image_path: { type: String, default: null },
  qris_image_url: { type: String, default: null },
  qris_checksum_sha256: { type: String, default: null },
  pending_qris_path: { type: String, default: null },
  pending_qris_checksum_sha256: { type: String, default: null },
  pending_qris_meta: { type: Object, default: null },
  can_approve_pending_qris: { type: Boolean, default: false },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);
const qrisImage = ref(null);
const removeQris = ref(false);
const selectedOutletId = ref(props.selected_outlet_id || 0);

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
  if (selectedOutletId.value) {
    fd.append('outlet_id', String(selectedOutletId.value));
  }
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

function handleOutletChange() {
  router.get('/web-profile/payment-settings', { outlet_id: selectedOutletId.value || undefined }, {
    preserveState: false,
    preserveScroll: true,
  });
}

function approvePending() {
  router.post('/web-profile/payment-settings/qris/approve', {
    outlet_id: selectedOutletId.value || null,
  });
}
</script>

<template>
  <AppLayout title="Web Profile - Payment QRIS">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan Payment QRIS</h1>

      <form class="bg-white rounded-lg shadow p-6 space-y-6" @submit.prevent="submit">
        <div>
          <InputLabel value="Outlet" />
          <select
            v-model.number="selectedOutletId"
            class="mt-1 block w-full rounded border-gray-300"
            @change="handleOutletChange"
          >
            <option :value="0">Default (Semua Outlet)</option>
            <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
              {{ outlet.nama_outlet }}
            </option>
          </select>
          <p class="mt-2 text-xs text-gray-500">Pilih outlet untuk upload QRIS khusus outlet tersebut.</p>
        </div>

        <div class="space-y-2">
          <h2 class="text-lg font-semibold text-gray-800">
            QRIS Reservasi
            <span v-if="selectedOutletId" class="text-sm font-normal text-gray-500">(Outlet spesifik)</span>
            <span v-else class="text-sm font-normal text-gray-500">(Default)</span>
          </h2>
          <p v-if="qris_checksum_sha256" class="text-xs text-gray-500 break-all">
            SHA256 aktif: {{ qris_checksum_sha256 }}
          </p>
        </div>

        <div v-if="pending_qris_path" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          <p class="font-semibold">Ada perubahan QRIS menunggu approval (maker-checker).</p>
          <p v-if="pending_qris_meta?.maker_name">Maker: {{ pending_qris_meta.maker_name }}</p>
          <p v-if="pending_qris_meta?.submitted_at">Diajukan: {{ pending_qris_meta.submitted_at }}</p>
          <p v-if="pending_qris_meta?.action">Aksi: {{ pending_qris_meta.action }}</p>
          <p v-if="pending_qris_checksum_sha256" class="break-all">SHA256 pending: {{ pending_qris_checksum_sha256 }}</p>
          <div v-if="can_approve_pending_qris" class="mt-2">
            <PrimaryButton type="button" @click="approvePending">Approve Perubahan QRIS</PrimaryButton>
          </div>
          <p v-else class="mt-1 text-xs">
            Approval harus oleh user lain (checker) yang berbeda dari maker.
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
