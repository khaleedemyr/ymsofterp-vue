<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-money-bill-wave"></i> Outlet Payments
        </h1>
        <div class="flex gap-2 items-center">
          <button 
            v-if="selectedPayments.length > 0" 
            @click="bulkConfirm" 
            class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-check mr-1"></i> Konfirmasi ({{ selectedPayments.length }})
          </button>
          <button @click="goToCreatePage" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Payment
          </button>
        </div>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <!-- Search Box -->
        <div class="flex-1 min-w-64">
          <input 
            type="text" 
            v-model="filters.search" 
            @input="onSearchChange"
            placeholder="Cari payment number, outlet, creator, GR/Retail number..." 
            class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          />
        </div>
        
        <!-- Filters -->
        <select v-model="filters.outlet" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Outlet</option>
          <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
        </select>
        <select v-model="filters.status" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="pending">Pending</option>
          <option value="paid">Paid</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input type="date" v-model="filters.date" @change="onFilterChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Tanggal" />
        
        <!-- Per Page Selector -->
        <select v-model="filters.per_page" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="10">10 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-yellow-100 to-yellow-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider rounded-tl-2xl">
                <input 
                  type="checkbox" 
                  v-model="selectAll" 
                  @change="toggleSelectAll"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
              </th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">No. Payment</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">Tanggal Transaksi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">Tanggal Payment</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">No. GR/Retail</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">Creator</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">Total Amount</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-yellow-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!payments.data || !payments.data.length">
              <td colspan="10" class="text-center py-10 text-gray-400">Belum ada data Payment.</td>
            </tr>
            <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-yellow-50 transition shadow-sm">
              <td class="px-6 py-3">
                <input 
                  type="checkbox" 
                  :value="payment.id"
                  v-model="selectedPayments"
                  :disabled="payment.status !== 'pending'"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
              </td>
              <td class="px-6 py-3 font-mono font-semibold text-yellow-700">{{ payment.payment_number }}</td>
              <td class="px-6 py-3">
                <div v-if="payment.payment_type === 'GR'">
                  <span class="text-blue-600 font-medium">{{ formatDate(payment.gr_date) }}</span>
                  <div class="text-xs text-gray-500">GR Date</div>
                </div>
                <div v-else-if="payment.payment_type === 'Retail'">
                  <span class="text-green-600 font-medium">{{ formatDate(payment.rws_date) }}</span>
                  <div class="text-xs text-gray-500">RWS Date</div>
                </div>
                <div v-else>
                  <span class="text-gray-400">{{ formatDate(payment.date) }}</span>
                </div>
              </td>
              <td class="px-6 py-3">
                <span class="text-purple-600 font-medium">{{ formatDate(payment.payment_created_at) }}</span>
                <div class="text-xs text-gray-500">Created</div>
              </td>
              <td class="px-6 py-3">{{ payment.outlet_name || '-' }}</td>
              <td class="px-6 py-3">
                <div v-if="payment.payment_type === 'GR'">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <i class="fa fa-box mr-1"></i> {{ payment.gr_number || '-' }}
                  </span>
                </div>
                <div v-else-if="payment.payment_type === 'Retail'">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fa fa-shopping-cart mr-1"></i> {{ payment.retail_number || '-' }}
                  </span>
                </div>
                <div v-else>
                  <span class="text-gray-400">-</span>
                </div>
              </td>
              <td class="px-6 py-3">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                  <i class="fa fa-user mr-1"></i> {{ payment.creator_name || '-' }}
                </span>
              </td>
              <td class="px-6 py-3 text-right">{{ formatCurrency(payment.total_amount) }}</td>
              <td class="px-6 py-3">
                <span :class="getStatusClass(payment.status)" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ payment.status }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="viewPayment(payment)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button v-if="payment.status === 'pending'" @click="editPayment(payment)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-pencil-alt mr-1"></i> Edit
                  </button>
                  <button v-if="payment.status === 'pending'" @click="deletePayment(payment)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
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
          Menampilkan {{ payments.from || 0 }} - {{ payments.to || 0 }} dari {{ payments.total || 0 }} data
        </div>
        <div class="flex gap-2">
          <button
            v-for="link in payments.links"
            :key="link.label"
            :disabled="!link.url"
            @click="goToPage(link.url)"
            v-html="link.label"
            class="px-3 py-1 rounded-lg border text-sm font-semibold"
            :class="[
              link.active ? 'bg-yellow-600 text-white shadow-lg' : 'bg-white text-yellow-700 hover:bg-yellow-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  payments: Object,
  outlets: Array,
  filters: Object,
  grGroups: Object
});

const filters = ref({
  outlet: props.filters?.outlet || '',
  status: props.filters?.status || '',
  date: props.filters?.date || '',
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || '10'
});

// Bulk selection
const selectedPayments = ref([]);
const selectAll = ref(false);

// Computed properties
const pendingPayments = computed(() => {
  return props.payments?.data?.filter(payment => payment.status === 'pending') || [];
});

// Watch for changes in selectedPayments to update selectAll
watch(selectedPayments, (newVal) => {
  selectAll.value = newVal.length > 0 && newVal.length === pendingPayments.value.length;
}, { deep: true });

// Watch for changes in selectAll
watch(selectAll, (newVal) => {
  if (newVal) {
    selectedPayments.value = pendingPayments.value.map(payment => payment.id);
  } else {
    selectedPayments.value = [];
  }
});

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function getStatusClass(status) {
  return {
    pending: 'bg-yellow-100 text-yellow-800',
    paid: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function onFilterChange() {
  router.get('/outlet-payments', { ...filters.value }, { preserveState: true, replace: true });
}

// Search with debounce
let searchTimeout = null;
function onSearchChange() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get('/outlet-payments', { ...filters.value }, { preserveState: true, replace: true });
  }, 500); // 500ms debounce
}

function goToCreatePage() {
  router.get('/outlet-payments/create');
}

function viewPayment(payment) {
  if (payment && payment.id) {
    router.get(`/outlet-payments/${payment.id}`);
  }
}

function editPayment(payment) {
  if (payment && payment.id) {
    router.get(`/outlet-payments/${payment.id}/edit`);
  }
}

function deletePayment(payment) {
  if (payment && payment.id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire({
        title: 'Hapus Payment?',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          router.delete(`/outlet-payments/${payment.id}`, {
            onSuccess: () => {
              Swal.fire('Berhasil', 'Payment berhasil dihapus!', 'success');
            },
            onError: () => {
              Swal.fire('Gagal', 'Gagal menghapus Payment', 'error');
            }
          });
        }
      });
    });
  }
}


function toggleSelectAll() {
  if (selectAll.value) {
    selectedPayments.value = pendingPayments.value.map(payment => payment.id);
  } else {
    selectedPayments.value = [];
  }
}

function bulkConfirm() {
  if (selectedPayments.value.length === 0) {
    return;
  }

  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Konfirmasi Pembayaran?',
      text: `Apakah Anda yakin ingin mengkonfirmasi ${selectedPayments.value.length} payment?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Konfirmasi!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post('/outlet-payments/bulk-confirm', {
          payment_ids: selectedPayments.value
        }, {
          onSuccess: () => {
            selectedPayments.value = [];
            selectAll.value = false;
            Swal.fire('Berhasil', 'Payments berhasil dikonfirmasi!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal mengkonfirmasi payments', 'error');
          }
        });
      }
    });
  });
}
</script> 