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

      <!-- Search dan Filter -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter & Pencarian</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="Cari item, batch, user, catatan..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <!-- Item Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Item</label>
            <select
              v-model="selectedItem"
              @change="onSearchInput"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Item</option>
              <option v-for="item in items" :key="item.id" :value="item.id">
                {{ item.name }}
              </option>
            </select>
          </div>
          
          <!-- Filter Tanggal Dari -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input
              v-model="fromDate"
              @change="onDateChange"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <!-- Filter Tanggal Sampai -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input
              v-model="toDate"
              @change="onDateChange"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Halaman</label>
            <select
              v-model="perPage"
              @change="onPerPageChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
          
          <!-- Tombol Clear -->
          <div>
            <button
              @click="clearFilters"
              class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
            >
              Clear Filter
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Jam</th>
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
              <td colspan="10" class="text-center py-10 text-blue-300">Tidak ada data produksi.</td>
            </tr>
            <tr v-for="prod in productions.data" :key="prod.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ formatDate(prod.production_date) }}</td>
              <td class="px-6 py-3">{{ formatTime(prod.created_at) }}</td>
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
      <!-- Pagination -->
      <div v-if="productions.last_page > 1" class="bg-white rounded-xl shadow-lg p-4 mt-6">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
          <!-- Info Pagination -->
          <div class="text-sm text-gray-700">
            Menampilkan {{ productions.from || 0 }} sampai {{ productions.to || 0 }} dari {{ productions.total }} data
          </div>
          
          <!-- Pagination Controls -->
          <div class="flex items-center gap-2">
            <!-- Previous Button -->
            <button
              @click="() => productions.prev_page_url && router.visit(productions.prev_page_url, { preserveState: true, replace: true })"
              :disabled="!productions.prev_page_url"
              :class="[
                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                productions.prev_page_url 
                  ? 'bg-blue-500 text-white hover:bg-blue-600' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              <i class="fa-solid fa-chevron-left mr-1"></i>
              Sebelumnya
            </button>
            
            <!-- Page Numbers -->
            <div class="flex items-center gap-1">
              <button
                v-for="link in productions.links"
                :key="link.label"
                :disabled="!link.url"
                @click="() => link.url && router.visit(link.url, { preserveState: true, replace: true })"
                v-html="link.label"
                class="px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                :class="[
                  link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                  !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                ]"
              />
            </div>
            
            <!-- Next Button -->
            <button
              @click="() => productions.next_page_url && router.visit(productions.next_page_url, { preserveState: true, replace: true })"
              :disabled="!productions.next_page_url"
              :class="[
                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                productions.next_page_url 
                  ? 'bg-blue-500 text-white hover:bg-blue-600' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              Selanjutnya
              <i class="fa-solid fa-chevron-right ml-1"></i>
            </button>
          </div>
        </div>
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
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MKProductionForm from './Form.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  productions: Object,
  items: Array,
  filters: Object
});


const showForm = ref(false);
const search = ref(props.filters?.search || '');
const selectedItem = ref(props.filters?.item_id || '');
const fromDate = ref(props.filters?.from_date || '');
const toDate = ref(props.filters?.to_date || '');
const perPage = ref(props.filters?.per_page || 15);

// Watch untuk auto search
watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    selectedItem.value = filters?.item_id || '';
    fromDate.value = filters?.from_date || '';
    toDate.value = filters?.to_date || '';
    perPage.value = filters?.per_page || 15;
  },
  { immediate: true }
);

function debouncedSearch() {
  router.get('/mk-production', { 
    search: search.value, 
    item_id: selectedItem.value,
    from_date: fromDate.value,
    to_date: toDate.value,
    per_page: perPage.value
  }, { preserveState: true, replace: true });
}

function onSearchInput() {
  debouncedSearch();
}

function onDateChange() {
  debouncedSearch();
}

function onPerPageChange() {
  debouncedSearch();
}

function clearFilters() {
  search.value = '';
  selectedItem.value = '';
  fromDate.value = '';
  toDate.value = '';
  perPage.value = 15;
  debouncedSearch();
}

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