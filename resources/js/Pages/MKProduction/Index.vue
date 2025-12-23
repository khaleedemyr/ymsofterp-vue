<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
              <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-industry text-white text-xl"></i>
              </div>
              <span>MK Production</span>
            </h1>
            <p class="text-gray-600 ml-16">Kelola produksi dan monitoring batch production</p>
          </div>
          <button 
            @click="goCreate" 
            class="group relative inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:-translate-y-0.5"
          >
            <i class="fa-solid fa-plus-circle text-lg"></i>
            <span>Create New Production</span>
          </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-blue-700 mb-1">Total Production</p>
                <p class="text-2xl font-bold text-blue-900">{{ productions.total || 0 }}</p>
              </div>
              <div class="p-3 bg-blue-500 rounded-lg">
                <i class="fa-solid fa-chart-line text-white text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 border border-green-200 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-green-700 mb-1">This Month</p>
                <p class="text-2xl font-bold text-green-900">{{ thisMonthCount }}</p>
              </div>
              <div class="p-3 bg-green-500 rounded-lg">
                <i class="fa-solid fa-calendar-check text-white text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 border border-purple-200 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-purple-700 mb-1">Showing</p>
                <p class="text-2xl font-bold text-purple-900">{{ productions.from || 0 }}-{{ productions.to || 0 }}</p>
              </div>
              <div class="p-3 bg-purple-500 rounded-lg">
                <i class="fa-solid fa-list text-white text-xl"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Search dan Filter -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-blue-100 rounded-lg">
            <i class="fa-solid fa-filter text-blue-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Filter & Pencarian</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
          <!-- Search -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-search text-blue-500 mr-1"></i> Cari
            </label>
            <div class="relative">
              <input
                v-model="search"
                @input="onSearchInput"
                type="text"
                placeholder="Cari item, batch, user, catatan..."
                class="w-full pl-10 pr-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              />
              <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
          </div>
          
          <!-- Item Filter -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-box text-blue-500 mr-1"></i> Item
            </label>
            <select
              v-model="selectedItem"
              @change="onSearchInput"
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white"
            >
              <option value="">Semua Item</option>
              <option v-for="item in items" :key="item.id" :value="item.id">
                {{ item.name }}
              </option>
            </select>
          </div>
          
          <!-- Filter Tanggal Dari -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-calendar-day text-blue-500 mr-1"></i> Dari Tanggal
            </label>
            <input
              v-model="fromDate"
              @change="onDateChange"
              type="date"
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
          </div>
          
          <!-- Filter Tanggal Sampai -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-calendar-check text-blue-500 mr-1"></i> Sampai Tanggal
            </label>
            <input
              v-model="toDate"
              @change="onDateChange"
              type="date"
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
          </div>
          
          <!-- Per Page -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-list-ol text-blue-500 mr-1"></i> Per Halaman
            </label>
            <select
              v-model="perPage"
              @change="onPerPageChange"
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white"
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
              class="w-full px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all font-semibold border-2 border-gray-200 hover:border-gray-300 flex items-center justify-center gap-2"
            >
              <i class="fa-solid fa-rotate-left"></i>
              Clear Filter
            </button>
          </div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-full">
            <thead class="bg-gradient-to-r from-blue-600 to-blue-700">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-calendar text-blue-200"></i>
                    <span>Tanggal</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock text-blue-200"></i>
                    <span>Jam</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-barcode text-blue-200"></i>
                    <span>Batch</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-box text-blue-200"></i>
                    <span>Item</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-calculator text-blue-200"></i>
                    <span>Qty</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-check-circle text-blue-200"></i>
                    <span>Qty Jadi</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-ruler text-blue-200"></i>
                    <span>Unit</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-user text-blue-200"></i>
                    <span>Created By</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-note-sticky text-blue-200"></i>
                    <span>Catatan</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-center text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-gear text-blue-200"></i>
                    <span>Aksi</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-if="productions.data.length === 0">
                <td colspan="10" class="px-6 py-16 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="p-4 bg-gray-100 rounded-full mb-4">
                      <i class="fa-solid fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-lg font-medium">Tidak ada data produksi</p>
                    <p class="text-gray-400 text-sm mt-1">Mulai dengan membuat produksi baru</p>
                  </div>
                </td>
              </tr>
              <tr 
                v-for="prod in productions.data" 
                :key="prod.id" 
                class="hover:bg-blue-50 transition-all duration-150 border-b border-gray-50"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-semibold text-gray-900">{{ formatDate(prod.production_date) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-700">{{ formatTime(prod.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                    <i class="fa-solid fa-barcode mr-1.5"></i>
                    {{ prod.batch_number }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900">{{ prod.item_name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-semibold text-gray-900">{{ Number(prod.qty).toFixed(2) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-semibold text-green-600">{{ Number(prod.qty_jadi).toFixed(2) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                    {{ prod.unit_name }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                      <i class="fa-solid fa-user text-blue-600 text-xs"></i>
                    </div>
                    <div class="text-sm text-gray-700">{{ prod.created_by_name }}</div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-600 max-w-xs truncate" :title="prod.notes">
                    {{ prod.notes || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button 
                      @click="goDetail(prod.id)" 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
                    >
                      <i class="fa-solid fa-eye"></i>
                      <span>Detail</span>
                    </button>
                    <button 
                      @click="onDelete(prod.id)" 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
                    >
                      <i class="fa-solid fa-trash"></i>
                      <span>Hapus</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <div v-if="productions.last_page > 1" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mt-6">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
          <!-- Info Pagination -->
          <div class="text-sm text-gray-700 font-medium">
            <i class="fa-solid fa-info-circle text-blue-500 mr-2"></i>
            Menampilkan <span class="font-bold text-blue-600">{{ productions.from || 0 }}</span> sampai 
            <span class="font-bold text-blue-600">{{ productions.to || 0 }}</span> dari 
            <span class="font-bold text-gray-900">{{ productions.total }}</span> data
          </div>
          
          <!-- Pagination Controls -->
          <div class="flex items-center gap-2">
            <!-- Previous Button -->
            <button
              @click="() => productions.prev_page_url && router.visit(productions.prev_page_url, { preserveState: true, replace: true })"
              :disabled="!productions.prev_page_url"
              :class="[
                'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200',
                productions.prev_page_url 
                  ? 'bg-blue-500 text-white hover:bg-blue-600 shadow-md hover:shadow-lg transform hover:-translate-y-0.5' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              <i class="fa-solid fa-chevron-left"></i>
              <span>Sebelumnya</span>
            </button>
            
            <!-- Page Numbers -->
            <div class="flex items-center gap-1.5">
              <button
                v-for="link in productions.links"
                :key="link.label"
                :disabled="!link.url"
                @click="() => link.url && router.visit(link.url, { preserveState: true, replace: true })"
                v-html="link.label"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 min-w-[40px]"
                :class="[
                  link.active 
                    ? 'bg-blue-600 text-white shadow-md' 
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 hover:shadow-sm',
                  !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:scale-105'
                ]"
              />
            </div>
            
            <!-- Next Button -->
            <button
              @click="() => productions.next_page_url && router.visit(productions.next_page_url, { preserveState: true, replace: true })"
              :disabled="!productions.next_page_url"
              :class="[
                'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200',
                productions.next_page_url 
                  ? 'bg-blue-500 text-white hover:bg-blue-600 shadow-md hover:shadow-lg transform hover:-translate-y-0.5' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              <span>Selanjutnya</span>
              <i class="fa-solid fa-chevron-right"></i>
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
import { ref, watch, computed } from 'vue';
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

// Computed untuk productions dengan default values
const productions = computed(() => props.productions || {
  data: [],
  total: 0,
  from: 0,
  to: 0,
  last_page: 1,
  links: [],
  prev_page_url: null,
  next_page_url: null,
});

// Computed untuk this month count
const thisMonthCount = computed(() => {
  if (!productions.value.data || productions.value.data.length === 0) return 0;
  const now = new Date();
  const currentMonth = now.getMonth();
  const currentYear = now.getFullYear();
  
  return productions.value.data.filter(prod => {
    const prodDate = new Date(prod.production_date);
    return prodDate.getMonth() === currentMonth && prodDate.getFullYear() === currentYear;
  }).length;
});

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
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/mk-production/${id}`)
        .then(() => {
          Swal.fire({
            title: 'Berhasil',
            text: 'Data berhasil dihapus & stok di-rollback',
            icon: 'success',
            confirmButtonColor: '#3b82f6',
          })
          router.reload()
        })
        .catch(() => {
          Swal.fire({
            title: 'Gagal',
            text: 'Gagal menghapus data',
            icon: 'error',
            confirmButtonColor: '#ef4444',
          })
        })
    }
  })
}
</script> 