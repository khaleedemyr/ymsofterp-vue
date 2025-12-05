<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-right-left"></i> Detail Pindah Gudang
        </h1>
        <div class="flex gap-2">
          <Link
            :href="route('warehouse-transfer.index')"
            class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-arrow-left mr-2"></i> Kembali
          </Link>
          <button
            @click="confirmDelete"
            class="bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-trash mr-2"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Transfer</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Nomor Transfer</label>
                <div class="mt-1 font-mono font-semibold text-blue-700">{{ transfer.transfer_number }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Tanggal Transfer</label>
                <div class="mt-1">{{ formatDate(transfer.transfer_date) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Status</label>
                <div class="mt-1">
                  <span :class="{
                    'bg-gray-100 text-gray-700': transfer.status === 'draft',
                    'bg-green-100 text-green-700': transfer.status === 'approved',
                    'bg-red-100 text-red-700': transfer.status === 'rejected',
                  }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                    {{ transfer.status }}
                  </span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Dibuat Oleh</label>
                <div class="mt-1">{{ transfer.creator?.nama_lengkap }}</div>
              </div>
            </div>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Gudang</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Gudang Asal</label>
                <div class="mt-1">{{ transfer.warehouse_from?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Gudang Tujuan</label>
                <div class="mt-1">{{ transfer.warehouse_to?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Keterangan</label>
                <div class="mt-1">{{ transfer.notes || '-' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Detail Item</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Keterangan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in transfer.items" :key="item.id" class="hover:bg-blue-50">
                <td class="px-6 py-4">
                  <div class="font-medium text-gray-900">{{ item.item?.name }}</div>
                  <div class="text-sm text-gray-500">{{ item.item?.sku }}</div>
                </td>
                <td class="px-6 py-4">{{ item.quantity }}</td>
                <td class="px-6 py-4">{{ item.unit?.name || '-' }}</td>
                <td class="px-6 py-4">{{ item.note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  transfer: Object,
});

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function confirmDelete() {
  Swal.fire({
    title: 'Apakah Anda yakin?',
    text: "Data yang dihapus tidak dapat dikembalikan!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('warehouse-transfer.destroy', props.transfer.id), {
        onSuccess: () => {
          Swal.fire(
            'Terhapus!',
            'Data berhasil dihapus.',
            'success'
          );
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'Terjadi kesalahan saat menghapus data.',
            'error'
          );
        }
      });
    }
  });
}
</script> 