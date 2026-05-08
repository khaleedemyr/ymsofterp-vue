<script setup>
import { onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  rows: { type: Array, default: () => [] },
});

const page = usePage();

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonText: 'OK' });
  }
  if (flash.error) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error, confirmButtonText: 'OK' });
  }
});

function goCreate(outletId) {
  router.get('/web-profile/payment-settings/create', {
    outlet_id: outletId || undefined,
  });
}

function goEdit(outletId) {
  router.get('/web-profile/payment-settings/edit', {
    outlet_id: outletId || undefined,
  });
}

async function approvePending(outletId) {
  const confirmed = await Swal.fire({
    icon: 'question',
    title: 'Approve perubahan QRIS?',
    text: 'Perubahan pending akan dijadikan QRIS aktif.',
    showCancelButton: true,
    confirmButtonText: 'Ya, approve',
    cancelButtonText: 'Tidak',
  });
  if (!confirmed.isConfirmed) return;

  router.post('/web-profile/payment-settings/qris/approve', {
    outlet_id: outletId || null,
  }, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Approve QRIS berhasil.' });
    },
  });
}

async function destroyQris(outletId) {
  const confirmed = await Swal.fire({
    icon: 'warning',
    title: 'Hapus QRIS?',
    text: 'QRIS aktif dan pending untuk outlet ini akan dihapus.',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirmed.isConfirmed) return;

  router.post('/web-profile/payment-settings/delete', {
    outlet_id: outletId || null,
  }, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'QRIS berhasil dihapus.' });
    },
  });
}
</script>

<template>
  <AppLayout title="Web Profile - Payment QRIS">
    <div class="max-w-6xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan Payment QRIS</h1>

      <div class="bg-white rounded-lg shadow p-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left py-2">Outlet</th>
              <th class="text-left py-2">Status</th>
              <th class="text-left py-2">QRIS Aktif</th>
              <th class="text-left py-2">Pending</th>
              <th class="text-left py-2">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="String(row.outlet_id ?? 'default')" class="border-b align-top">
              <td class="py-3 pr-3 font-semibold">{{ row.outlet_name }}</td>
              <td class="py-3 pr-3">
                <span
                  v-if="row.pending_path"
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800"
                >
                  Pending Approval
                </span>
                <span
                  v-else-if="row.active_url"
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800"
                >
                  Active
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700"
                >
                  No QRIS
                </span>
              </td>
              <td class="py-3 pr-3">
                <div v-if="row.active_url" class="space-y-1">
                  <img :src="row.active_url" alt="QRIS aktif" class="h-20 w-20 object-contain border rounded bg-gray-50" />
                  <div class="text-[11px] text-gray-500 break-all">{{ row.active_hash || '-' }}</div>
                </div>
                <div v-else class="text-gray-400">Belum ada</div>
              </td>
              <td class="py-3 pr-3">
                <div v-if="row.pending_path" class="text-amber-700">
                  <div class="font-semibold">Menunggu approval</div>
                  <div class="text-xs">Maker: {{ row.pending_meta?.maker_name || '-' }}</div>
                  <div class="text-xs">Aksi: {{ row.pending_meta?.action || '-' }}</div>
                </div>
                <div v-else class="text-gray-400">Tidak ada</div>
              </td>
              <td class="py-3">
                <div class="flex flex-wrap gap-2">
                  <PrimaryButton type="button" @click="goCreate(row.outlet_id)">Create</PrimaryButton>
                  <PrimaryButton type="button" @click="goEdit(row.outlet_id)">Edit</PrimaryButton>
                  <button
                    class="px-3 py-1.5 rounded bg-red-600 text-white disabled:opacity-50"
                    type="button"
                    :disabled="!row.can_delete"
                    @click="destroyQris(row.outlet_id)"
                  >
                    Delete
                  </button>
                  <button
                    v-if="row.pending_path"
                    class="px-3 py-1.5 rounded bg-amber-600 text-white disabled:opacity-50"
                    type="button"
                    :disabled="!row.can_approve"
                    @click="approvePending(row.outlet_id)"
                  >
                    Approve
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
