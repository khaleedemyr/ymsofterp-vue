<script setup>
import { ref, onMounted, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { debounce } from 'lodash';

const props = defineProps({
  purchaseOrders: Object,
  filters: Object,
  user: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const perPage = ref(props.filters?.perPage || 10);
const deletingPO = ref(false);

// Print functionality
const showPrintModal = ref(false);
const printData = ref([]);
const previewUrl = ref('');
const previewFrame = ref(null);

const debouncedSearch = debounce(() => {
  // Simpan filter state ke sessionStorage
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value
  };
  sessionStorage.setItem('po-ops-filters', JSON.stringify(filterState));
  
  router.get('/po-ops', filterState, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}
function onStatusChange() {
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
  selectedStatus.value = '';
  from.value = '';
  to.value = '';
  perPage.value = 10;
  debouncedSearch();
}

function getStatusColor(status) {
  const colors = {
    'draft': 'bg-gray-100 text-gray-800',
    'submitted': 'bg-yellow-100 text-yellow-800',
    'approved': 'bg-green-100 text-green-800',
    'rejected': 'bg-red-100 text-red-800',
    'received': 'bg-blue-100 text-blue-800',
    'cancelled': 'bg-gray-100 text-gray-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}


function deletePO(po) {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    html: `
      <div class="text-left">
        <p class="mb-2">Apakah Anda yakin ingin menghapus Purchase Order berikut?</p>
        <div class="bg-gray-100 p-3 rounded-lg">
          <p><strong>PO Number:</strong> ${po.number}</p>
          <p><strong>Supplier:</strong> ${po.supplier?.name || 'Unknown'}</p>
          <p><strong>Total:</strong> Rp ${new Intl.NumberFormat('id-ID').format(po.grand_total)}</p>
          <p><strong>Status:</strong> <span class="px-2 py-1 rounded text-xs ${getStatusColor(po.status)}">${po.status.toUpperCase()}</span></p>
        </div>
        <p class="mt-2 text-red-600 text-sm"><i class="fas fa-exclamation-triangle mr-1"></i>Tindakan ini tidak dapat dibatalkan!</p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<i class="fas fa-trash mr-2"></i>Ya, Hapus!',
    cancelButtonText: '<i class="fas fa-times mr-2"></i>Batal',
    showLoaderOnConfirm: true,
           preConfirm: () => {
             deletingPO.value = true;
             return new Promise((resolve, reject) => {
               router.delete(`/po-ops/${po.id}`, {
                 onSuccess: (page) => {
                   deletingPO.value = false;
                   resolve();
                 },
                 onError: (errors) => {
                   deletingPO.value = false;
                   reject(new Error('Gagal menghapus PO. Silakan coba lagi.'));
                 }
               });
             });
           },
    allowOutsideClick: () => !deletingPO.value
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Berhasil!',
        text: `Purchase Order ${po.number} berhasil dihapus.`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
    }
  }).catch((error) => {
    Swal.fire({
      title: 'Error!',
      text: error.message || 'Gagal menghapus Purchase Order.',
      icon: 'error',
      confirmButtonText: 'OK'
    });
  });
}

function openCreate() {
  router.visit('/po-ops/create');
}

// Print functionality
async function printSinglePO(po) {
  try {
    printData.value = [po];
    
    // Generate preview URL
    const poIds = po.id.toString();
    previewUrl.value = `/po-ops/print-preview?ids=${encodeURIComponent(poIds)}`;
    showPrintModal.value = true;
  } catch (error) {
    console.error('Error preparing print:', error);
    Swal.fire('Error', 'Gagal mempersiapkan print', 'error');
  }
}

function closePrintModal() {
  showPrintModal.value = false;
  previewUrl.value = '';
  printData.value = [];
}

function printPreview() {
  if (previewFrame.value) {
    previewFrame.value.contentWindow.print();
  }
}

// Load filter state from sessionStorage on mount
onMounted(() => {
  const savedFilters = sessionStorage.getItem('po-ops-filters');
  if (savedFilters) {
    const filters = JSON.parse(savedFilters);
    search.value = filters.search || '';
    selectedStatus.value = filters.status || '';
    from.value = filters.from || '';
    to.value = filters.to || '';
    perPage.value = filters.perPage || 10;
  }
});
</script>

<template>
  <AppLayout title="Purchase Order Ops">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-shopping-cart text-blue-500"></i> Purchase Order Ops
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Purchase Order Baru
        </button>
      </div>

      <!-- Filter and Search -->
      <div class="flex flex-col md:flex-row gap-4 mb-6">
        <input
          type="text"
          v-model="search"
          @input="onSearchInput"
          placeholder="Cari PO number, supplier, PR number, outlet..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select
          v-model="selectedStatus"
          @change="onStatusChange"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="received">Received</option>
          <option value="rejected">Rejected</option>
        </select>
        <input
          type="date"
          v-model="from"
          @change="onDateChange"
          placeholder="Dari Tanggal"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input
          type="date"
          v-model="to"
          @change="onDateChange"
          placeholder="Sampai Tanggal"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select
          v-model="perPage"
          @change="onPerPageChange"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="10">10 Per Halaman</option>
          <option value="25">25 Per Halaman</option>
          <option value="50">50 Per Halaman</option>
        </select>
        <button
          @click="clearFilters"
          class="w-full md:w-auto px-4 py-2 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition"
        >
          Clear Filters
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source PR</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="po in purchaseOrders.data" :key="po.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ po.number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(po.date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ po.supplier?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span v-if="po.source_pr_number" class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                    {{ po.source_pr_number }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span v-if="po.outlet?.nama_outlet" class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded">
                    {{ po.outlet.nama_outlet }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatCurrency(po.grand_total) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusColor(po.status)">
                    {{ po.status.toUpperCase() }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ po.creator?.nama_lengkap || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex space-x-2">
                    <Link
                      :href="`/po-ops/${po.id}`"
                      class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded hover:bg-blue-200 transition-colors"
                      title="Lihat Detail"
                    >
                      <i class="fas fa-eye mr-1"></i>
                      Detail
                    </Link>
                    <button
                      @click="printSinglePO(po)"
                      class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded hover:bg-purple-200 transition-colors"
                      title="Print PDF"
                    >
                      <i class="fas fa-print mr-1"></i>
                      Print
                    </button>
                    <button
                      v-if="po.status === 'draft' || po.status === 'approved'"
                      @click="deletePO(po)"
                      :disabled="deletingPO"
                      class="inline-flex items-center px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      title="Hapus Purchase Order"
                    >
                      <i v-if="deletingPO" class="fas fa-spinner fa-spin mr-1"></i>
                      <i v-else class="fas fa-trash mr-1"></i>
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="purchaseOrders.links" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="flex-1 flex justify-between sm:hidden">
            <Link
              v-if="purchaseOrders.prev_page_url"
              :href="purchaseOrders.prev_page_url"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Previous
            </Link>
            <Link
              v-if="purchaseOrders.next_page_url"
              :href="purchaseOrders.next_page_url"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Next
            </Link>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Showing
                <span class="font-medium">{{ purchaseOrders.from }}</span>
                to
                <span class="font-medium">{{ purchaseOrders.to }}</span>
                of
                <span class="font-medium">{{ purchaseOrders.total }}</span>
                results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="link in purchaseOrders.links" :key="link.label">
                  <Link
                    v-if="link.url"
                    :href="link.url"
                    v-html="link.label"
                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                    :class="link.active ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                  />
                  <span
                    v-else
                    v-html="link.label"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
                  />
                </template>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Print Preview Modal -->
    <div v-if="showPrintModal" class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-6xl p-6 relative">
        <button @click="closePrintModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Preview Purchase Order</h3>
          <div class="flex gap-2">
            <button 
              @click="printPreview"
              class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 flex items-center gap-1"
            >
              <i class="fas fa-print"></i>
              Print
            </button>
          </div>
        </div>
        <div class="p-4" style="height: 80vh;">
          <iframe 
            :src="previewUrl" 
            class="w-full h-full border-0" 
            ref="previewFrame"
          ></iframe>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
