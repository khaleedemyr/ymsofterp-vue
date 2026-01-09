<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck-arrow-right text-blue-500"></i> Delivery Order
        </h1>
        <div class="flex gap-2">
          <button @click="exportToExcel" :disabled="exporting" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i v-if="exporting" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-file-excel"></i>
            {{ exporting ? 'Exporting...' : 'Export Excel' }}
          </button>
          <button @click="exportSummary" :disabled="exportingSummary" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i v-if="exportingSummary" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-chart-bar"></i>
            {{ exportingSummary ? 'Exporting...' : 'Export Summary' }}
          </button>
          <button @click="exportDetail" :disabled="exportingDetail" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i v-if="exportingDetail" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-list-ul"></i>
            {{ exportingDetail ? 'Exporting...' : 'Export Detail' }}
          </button>
          <Link href="/delivery-order/create" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Delivery Order
          </Link>
        </div>
      </div>
      <!-- Filter Form -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa fa-filter text-blue-500"></i>
          Filter Data
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input 
              v-model="search" 
              type="text" 
              placeholder="Cari nomor, outlet, warehouse..." 
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" 
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input 
              v-model="dateFrom" 
              type="date" 
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" 
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input 
              v-model="dateTo" 
              type="date" 
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" 
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
            <select 
              v-model="perPage" 
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            >
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3">
          <button 
            @click="loadDataWithFilters" 
            class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2"
          >
            <i class="fa fa-search"></i>
            Load Data
          </button>
          <button 
            @click="clearFilters" 
            class="bg-gradient-to-r from-gray-500 to-gray-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2"
          >
            <i class="fa fa-refresh"></i>
            Clear Filter
          </button>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No DO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal & Jam</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse Info</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Packing List</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Floor Order</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!loadData">
              <td colspan="9" class="text-center py-16">
                <div class="flex flex-col items-center gap-4">
                  <i class="fa fa-search text-6xl text-gray-300"></i>
                  <div class="text-gray-500">
                    <p class="text-lg font-semibold">Pilih filter dan klik "Load Data" untuk menampilkan data</p>
                    <p class="text-sm">Anda bisa search berdasarkan nomor, outlet, atau warehouse</p>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-else-if="!orders.data.length">
              <td colspan="9" class="text-center py-16">
                <div class="flex flex-col items-center gap-4">
                  <i class="fa fa-inbox text-6xl text-gray-300"></i>
                  <div class="text-gray-500">
                    <p class="text-lg font-semibold">Tidak ada data Delivery Order</p>
                    <p class="text-sm">Coba ubah filter atau tanggal pencarian</p>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-for="(order, idx) in orders.data" :key="order.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ (orders.current_page - 1) * orders.per_page + idx + 1 }}</td>
              <td class="px-6 py-3">{{ order.number || '-' }}</td>
              <td class="px-6 py-3">
                <div class="text-sm">
                  <div class="font-medium">{{ order.created_date || formatDate(order.created_at) }}</div>
                  <div class="text-gray-500 text-xs">{{ order.created_time || formatTime(order.created_at) }}</div>
                </div>
              </td>
              <td class="px-6 py-3">{{ order.nama_outlet || '-' }}</td>
              <td class="px-6 py-3">{{ order.warehouse_outlet_name || '-' }}</td>
              <td class="px-6 py-3">
                <div class="text-sm">
                  <div class="font-medium">{{ order.warehouse_info || '-' }}</div>
                </div>
              </td>
              <td class="px-6 py-3">{{ order.packing_number || '-' }}</td>
              <td class="px-6 py-3">{{ order.floor_order_number || '-' }}</td>
              <td class="px-6 py-3">{{ order.created_by_name || '-' }}</td>
              <td class="px-6 py-3">
                <Link :href="`/delivery-order/${order.id}`" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </Link>
                <button @click="handleReprint(order.id)" :disabled="loadingReprintId === order.id" class="ml-2 inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingReprintId === order.id" class="fa fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fa fa-print mr-1"></i> Reprint
                </button>
                <button 
                  v-if="canDelete"
                  @click="handleDelete(order.id)" 
                  :disabled="loadingDeleteId === order.id" 
                  class="ml-2 inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingDeleteId === order.id" class="fa fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fa fa-trash mr-1"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="loadData && orders.total > orders.per_page" class="flex flex-col items-center mt-6 space-y-4">
        <nav class="flex items-center space-x-1 flex-wrap justify-center" aria-label="Pagination">
          <!-- Previous button -->
          <button 
            @click="goToPage(orders.current_page - 1)" 
            :disabled="orders.current_page === 1"
            :class="[
              'px-3 py-2 text-sm font-medium rounded-md transition-colors',
              orders.current_page === 1 
                ? 'text-gray-400 cursor-not-allowed' 
                : 'text-blue-700 hover:bg-blue-100'
            ]"
          >
            <i class="fas fa-chevron-left"></i>
          </button>

          <!-- First page -->
          <button 
            v-if="orders.current_page > 3" 
            @click="goToPage(1)" 
            class="px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 rounded-md transition-colors"
          >
            1
          </button>

          <!-- Ellipsis after first page -->
          <span v-if="orders.current_page > 4" class="px-2 py-2 text-gray-500">...</span>

          <!-- Pages around current page -->
          <button 
            v-for="page in visiblePages" 
            :key="page" 
            @click="goToPage(page)" 
            :class="[
              'px-3 py-2 text-sm font-medium rounded-md transition-colors',
              page === orders.current_page 
                ? 'bg-blue-500 text-white' 
                : 'text-blue-700 hover:bg-blue-100'
            ]"
          >
            {{ page }}
          </button>

          <!-- Ellipsis before last page -->
          <span v-if="orders.current_page < orders.last_page - 3" class="px-2 py-2 text-gray-500">...</span>

          <!-- Last page -->
          <button 
            v-if="orders.current_page < orders.last_page - 2" 
            @click="goToPage(orders.last_page)" 
            class="px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 rounded-md transition-colors"
          >
            {{ orders.last_page }}
          </button>

          <!-- Next button -->
          <button 
            @click="goToPage(orders.current_page + 1)" 
            :disabled="orders.current_page === orders.last_page"
            :class="[
              'px-3 py-2 text-sm font-medium rounded-md transition-colors',
              orders.current_page === orders.last_page 
                ? 'text-gray-400 cursor-not-allowed' 
                : 'text-blue-700 hover:bg-blue-100'
            ]"
          >
            <i class="fas fa-chevron-right"></i>
          </button>
        </nav>

        <!-- Page info -->
        <div class="text-sm text-gray-600 text-center">
          Halaman {{ orders.current_page }} dari {{ orders.last_page }}
          <span class="mx-2">•</span>
          Total {{ orders.total }} data
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { generateStrukPDF } from './generateStrukPDF';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ 
  orders: Array,
  filters: Object
});
const loadingDeleteId = ref(null);
const loadingReprintId = ref(null);
const exporting = ref(false);
const exportingSummary = ref(false);
const exportingDetail = ref(false);

const page = usePage();
const user = computed(() => page.props.auth?.user || page.props.user);

// Check if user can delete Delivery Order
// Allow delete for: Superadmin (id_role = '5af56935b011a') or User with division_id=11 and status='A'
const canDelete = computed(() => {
  if (!user.value) return false;
  
  const isSuperadmin = user.value.id_role === '5af56935b011a';
  const isDivision11 = user.value.division_id === 11 && user.value.status === 'A';
  
  return isSuperadmin || isDivision11;
});

const search = ref(props.filters?.search || '');
// Set default ke hari ini jika tidak ada filter
const today = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
const dateFrom = ref(props.filters?.dateFrom || today);
const dateTo = ref(props.filters?.dateTo || today);
const loadData = ref(props.filters?.load_data || '');
const perPage = ref(props.filters?.per_page || 15);

// Computed property untuk halaman yang ditampilkan
const visiblePages = computed(() => {
  const current = props.orders.current_page;
  const last = props.orders.last_page;
  const delta = 2; // Jumlah halaman di kiri dan kanan current page
  
  let start = Math.max(1, current - delta);
  let end = Math.min(last, current + delta);
  
  // Jika terlalu dekat dengan awal
  if (current - delta <= 1) {
    end = Math.min(last, 1 + delta * 2);
  }
  
  // Jika terlalu dekat dengan akhir
  if (current + delta >= last) {
    start = Math.max(1, last - delta * 2);
  }
  
  const pages = [];
  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  
  return pages;
});

// Watch per_page changes to reload data
watch(perPage, (newPerPage) => {
  if (loadData.value === '1') {
    loadDataWithFilters();
  }
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

async function handleDelete(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Delivery Order?',
    text: 'Data dan rollback stok akan dikembalikan. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  loadingDeleteId.value = id;
  try {
    await router.delete(`/delivery-order/${id}`, {
      onSuccess: async () => {
        await Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: 'Delivery Order berhasil dihapus dan stok dikembalikan!',
          timer: 1500,
          showConfirmButton: false
        });
      },
      onError: async (err) => {
        await Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: err || 'Gagal menghapus Delivery Order',
        });
      },
      preserveScroll: true,
    });
  } finally {
    loadingDeleteId.value = null;
  }
}

async function handleReprint(orderId) {
  loadingReprintId.value = orderId;
  try {
    const { data } = await axios.get(`/api/delivery-order/${orderId}/struk`);
    await generateStrukPDF({
      ...data,
      showReprintLabel: true
    });
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: 'Gagal mengambil data struk. Coba lagi.'
    });
  } finally {
    loadingReprintId.value = null;
  }
}

function loadDataWithFilters() {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    load_data: '1',
    per_page: perPage.value
  }, { preserveState: true });
}

function clearFilters() {
  search.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  loadData.value = '';
  perPage.value = 15;
  
  // Call backend method to clear session filters
  router.get(route('delivery-order.clear-filters'), {}, { 
    preserveState: false, 
    replace: true 
  });
}

function goToPage(page) {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    load_data: loadData.value, // FIXED: Add load_data parameter
    per_page: perPage.value,   // FIXED: Add per_page parameter
    page
  }, { preserveState: true });
}

async function exportToExcel() {
  exporting.value = true;
  try {
    const response = await axios.get(route('delivery-order.export'), {
      params: {
        search: search.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
      },
      responseType: 'blob'
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `delivery-order-${new Date().toISOString().slice(0, 10)}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    await Swal.fire({
      icon: 'success',
      title: 'Export Berhasil',
      text: 'File Excel berhasil diunduh',
      timer: 1500,
      showConfirmButton: false
    });
  } catch (error) {
    console.error('Export error:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Export Gagal',
      text: 'Gagal mengekspor data ke Excel. Silakan coba lagi.',
    });
  } finally {
    exporting.value = false;
  }
}

async function exportSummary() {
  exportingSummary.value = true;
  try {
    const response = await axios.get(route('delivery-order.export-summary'), {
      params: {
        search: search.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
      },
      responseType: 'blob'
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `delivery-order-summary-${new Date().toISOString().slice(0, 10)}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    await Swal.fire({
      icon: 'success',
      title: 'Export Berhasil',
      text: 'File Summary berhasil diunduh',
      timer: 1500,
      showConfirmButton: false
    });
  } catch (error) {
    console.error('Export error:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Export Gagal',
      text: 'Gagal mengekspor data summary. Silakan coba lagi.',
    });
  } finally {
    exportingSummary.value = false;
  }
}

async function exportDetail() {
  exportingDetail.value = true;
  try {
    const response = await axios.get(route('delivery-order.export-detail'), {
      params: {
        search: search.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
      },
      responseType: 'blob'
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `delivery-order-detail-${new Date().toISOString().slice(0, 10)}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    await Swal.fire({
      icon: 'success',
      title: 'Export Berhasil',
      text: 'File Detail berhasil diunduh',
      timer: 1500,
      showConfirmButton: false
    });
  } catch (error) {
    console.error('Export error:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Export Gagal',
      text: 'Gagal mengekspor data detail. Silakan coba lagi.',
    });
  } finally {
    exportingDetail.value = false;
  }
}
</script> 