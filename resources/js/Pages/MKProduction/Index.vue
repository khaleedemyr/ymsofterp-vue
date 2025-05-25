<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-industry text-blue-500"></i> MK Production
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Create New
        </button>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Batch</th>
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
            <tr v-if="productions.data.length === 0">
              <td colspan="9" class="text-center py-10 text-blue-300">Tidak ada data produksi.</td>
            </tr>
            <tr v-for="prod in productions.data" :key="prod.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ formatDate(prod.production_date) }}</td>
              <td class="px-6 py-3">{{ prod.batch_number }}</td>
              <td class="px-6 py-3">{{ prod.item_name }}</td>
              <td class="px-6 py-3">{{ Number(prod.qty).toFixed(2) }}</td>
              <td class="px-6 py-3">{{ Number(prod.qty_jadi).toFixed(2) }}</td>
              <td class="px-6 py-3">{{ prod.unit_name }}</td>
              <td class="px-6 py-3">{{ prod.created_by_name }}</td>
              <td class="px-6 py-3">{{ prod.notes }}</td>
              <td class="px-6 py-3">
                <button @click="goDetail(prod.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </button>
                <button @click="onDelete(prod.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-2">
                  <i class="fa fa-trash mr-1"></i> Hapus
                </button>
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
      <div v-if="showForm">
        <!-- Form produksi, bisa pakai komponen terpisah atau modal -->
        <div class="fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
          <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 relative max-h-[90vh] overflow-y-auto">
            <button @click="showForm = false" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
              <i class="fa-solid fa-xmark text-2xl"></i>
            </button>
            <MKProductionForm :items="items" @success="onFormSuccess" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MKProductionForm from './Form.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  productions: Object,
  items: Array
});
const showForm = ref(false);
function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}
function onFormSuccess() {
  showForm.value = false;
  router.reload();
}
function goCreate() {
  router.visit(route('mk-production.create'))
}
function goDetail(id) {
  router.visit(route('mk-production.show', id))
}
function onDelete(id) {
  Swal.fire({
    title: 'Hapus Produksi?',
    text: 'Data dan stok akan di-rollback. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/mk-production/${id}`)
        .then(() => {
          Swal.fire('Berhasil', 'Data berhasil dihapus & stok di-rollback', 'success')
          router.reload()
        })
        .catch(() => {
          Swal.fire('Gagal', 'Gagal menghapus data', 'error')
        })
    }
  })
}
</script> 