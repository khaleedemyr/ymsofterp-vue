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

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="filters.search"
              @input="applyFilters"
              placeholder="Cari nomor, batch, outlet, warehouse, status..."
              class="input input-bordered w-full"
            />
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
            <input
              type="date"
              v-model="filters.date_from"
              @change="applyFilters"
              class="input input-bordered w-full"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
            <input
              type="date"
              v-model="filters.date_to"
              @change="applyFilters"
              class="input input-bordered w-full"
            />
          </div>
        </div>

        <div class="flex justify-between items-center mt-4">
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">Per Page:</label>
            <select
              v-model="filters.per_page"
              @change="applyFilters"
              class="input input-bordered w-20"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
          <button
            @click="resetFilters"
            class="btn btn-sm btn-outline"
          >
            Reset Filter
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Batch</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item Produksi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Catatan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="header in headers.data" :key="header.id" class="bg-white hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span 
                  class="font-semibold"
                  :class="{
                    'text-orange-600': header.number && header.number.startsWith('DRAFT-'),
                    'text-blue-600': header.number && !header.number.startsWith('DRAFT-'),
                    'text-gray-500': !header.number
                  }"
                >
                  {{ header.number || (header.source_type === 'old' ? 'Data Lama' : '-') }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(header.production_date) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span 
                  class="px-2 py-1 rounded text-xs font-semibold"
                  :class="{
                    'bg-orange-100 text-orange-700': header.status === 'DRAFT',
                    'bg-blue-100 text-blue-700': header.status === 'SUBMITTED',
                    'bg-green-100 text-green-700': header.status === 'PROCESSED'
                  }"
                >
                  {{ header.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ header.batch_number || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ header.outlet_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ header.warehouse_outlet_name }}</td>
              <td class="px-6 py-4 text-sm text-gray-900">
                <div v-if="getProductions(header.id).length > 0" class="space-y-1">
                  <div v-for="(prod, idx) in getProductions(header.id)" :key="`${header.id}-${prod.item_id}-${idx}`" class="text-xs">
                    <span class="font-medium">{{ prod.item_name }}</span>
                    <span class="text-gray-500 ml-2">
                      (Qty: {{ formatNumber(prod.qty) }}, Jadi: {{ formatNumber(prod.qty_jadi) }} {{ prod.unit_name }})
                    </span>
                  </div>
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ header.created_by_name }}</td>
              <td class="px-6 py-4 text-sm text-gray-900">{{ header.notes || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex items-center gap-2">
                  <button @click="goDetail(header.id)" class="text-blue-600 hover:text-blue-900" title="Detail">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  <button 
                    v-if="header.status === 'DRAFT' && header.source_type !== 'old'"
                    @click="goEdit(header.id)" 
                    class="text-green-600 hover:text-green-900"
                    title="Edit"
                  >
                    <i class="fa-solid fa-edit"></i>
                  </button>
                  <button @click="onDelete(header.id)" class="text-red-600 hover:text-red-900" title="Hapus">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
          Menampilkan {{ headers.from || 0 }} sampai {{ headers.to || 0 }} dari {{ headers.total || 0 }} data
        </div>
        <div class="flex gap-2">
          <button
            v-for="link in headers.links"
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
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  headers: Object,
  productionsByHeader: Object,
  filters: Object,
});

const filters = useForm({
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || 10,
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function getProductions(headerId) {
  return props.productionsByHeader[headerId] || [];
}

function applyFilters() {
  filters.get(route('outlet-wip.index'), {
    preserveState: true,
    preserveScroll: true,
  });
}

function resetFilters() {
  filters.reset();
  filters.get(route('outlet-wip.index'), {
    preserveState: true,
    preserveScroll: true,
  });
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

function goEdit(id) {
  router.visit(route('outlet-wip.edit', id))
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

// Debounce search input
let searchTimeout = null;
watch(() => filters.search, () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 500);
});
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500;
}

.btn {
  @apply px-4 py-2 rounded-md font-medium transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-sm;
}

.btn-outline {
  @apply border border-gray-300 text-gray-700 hover:bg-gray-50;
}
</style>
