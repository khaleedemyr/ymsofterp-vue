<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-industry text-blue-500"></i> Outlet WIP Production
        </h1>
        <div class="flex gap-3">
          <button @click="goReport" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-chart-bar mr-2"></i> Laporan
          </button>
          <button @click="goCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Create New
          </button>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Jam</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Batch</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Jadi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Catatan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="production in productions.data" :key="production.id" class="bg-white hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(production.production_date) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatTime(production.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.batch_number || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.outlet_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.warehouse_outlet_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.item_name }}</td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatNumber(production.qty) }}</td>
               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatNumber(production.qty_jadi) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.unit_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.created_by_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.notes || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex items-center gap-2">
                  <button @click="goDetail(production.id)" class="text-blue-600 hover:text-blue-900">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  <button @click="onDelete(production.id)" class="text-red-600 hover:text-red-900">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in productions.links"
          :key="link.label"
          :disabled="!link.url"
          @click="() => link.url && router.visit(link.url, { preserveState: true, replace: true })"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  productions: Object,
  items: Array
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function goCreate() {
  router.visit(route('outlet-wip.create'))
}

function goReport() {
  router.visit(route('outlet-wip.report'))
}

function goDetail(id) {
  router.visit(route('outlet-wip.show', id))
}

function onDelete(id) {
  Swal.fire({
    title: 'Hapus Produksi WIP?',
    text: "Data yang dihapus tidak dapat dikembalikan!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(route('outlet-wip.destroy', id))
        .then(response => {
          if (response.data.success) {
            Swal.fire(
              'Terhapus!',
              'Data produksi WIP berhasil dihapus.',
              'success'
            );
            router.reload();
          } else {
            Swal.fire(
              'Error!',
              response.data.message || 'Terjadi kesalahan saat menghapus data.',
              'error'
            );
          }
        })
        .catch(error => {
          console.error('Error deleting production:', error);
          Swal.fire(
            'Error!',
            'Terjadi kesalahan saat menghapus data.',
            'error'
          );
        });
    }
  });
}
</script>
