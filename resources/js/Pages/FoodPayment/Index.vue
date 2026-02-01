<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-money-check-dollar"></i> Food Payment
        </h1>
        <Link
          :href="route('food-payments.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Food Payment
        </Link>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <!-- Search Box -->
        <div class="flex-1 min-w-64">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Cari semua kolom (nomor, tanggal, supplier, payment type, total, status, pembuat, dll)..."
            class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          />
        </div>
        
        <!-- Filters -->
        <select v-model="filters.status" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="paid">Paid</option>
        </select>
        <input type="date" v-model="filters.date_from" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari Tanggal" title="Dari Tanggal" />
        <input type="date" v-model="filters.date_to" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai Tanggal" title="Sampai Tanggal" />
        
        <!-- Load Data Button -->
        <button 
          @click="loadData" 
          :disabled="isLoading"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <i v-if="!isLoading" class="fa fa-download"></i> 
          <img v-else src="/images/logo-icon.png" alt="Loading" class="w-4 h-4 animate-spin-fast" />
          {{ isLoading ? 'Memuat...' : 'Load Data' }}
        </button>
        
        <!-- Per Page Selector -->
        <select v-model="filters.per_page" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="10">10 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
      </div>
      
      <!-- Loading Spinner di tengah halaman -->
      <template v-if="isLoading && !isDataLoaded">
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-8 text-center">
          <img src="/images/logo-icon.png" alt="Loading" class="w-16 h-16 mx-auto mb-4 animate-spin" />
          <p class="text-gray-700 text-lg font-semibold mb-2">Memuat Data...</p>
          <p class="text-gray-600">Mohon tunggu sebentar.</p>
        </div>
      </template>

      <!-- Show message jika data belum di-load dan tidak sedang loading -->
      <template v-else-if="!isDataLoaded && !isLoading">
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-8 text-center">
          <i class="fa fa-info-circle text-blue-500 text-4xl mb-4"></i>
          <p class="text-gray-700 text-lg font-semibold mb-2">Data Belum Dimuat</p>
          <p class="text-gray-600 mb-4">Klik tombol "Load Data" untuk memuat data Food Payment</p>
          <button 
            @click="loadData" 
            class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-download mr-2"></i> Load Data
          </button>
        </div>
      </template>
      
      <!-- Table hanya muncul jika data sudah di-load -->
      <template v-else>
        <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Location</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Payment Type</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No Invoice</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!safePayments.data || !safePayments.data.length">
              <td colspan="10" class="text-center py-10 text-gray-400">Belum ada data Food Payment.</td>
            </tr>
            <template v-else>
              <tr v-for="p in safePayments.data" :key="p.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ p.number }}</td>
              <td class="px-6 py-3">{{ formatDate(p.date) }}</td>
              <td class="px-6 py-3">{{ p.supplier?.name }}</td>
              <td class="px-6 py-3">
                <div v-if="p.locations && p.locations.length > 0" class="flex flex-col gap-1">
                  <!-- Single location -->
                  <template v-if="p.locations.length === 1">
                    <span class="text-xs font-semibold" :class="p.locations[0].type === 'outlet' ? 'text-blue-700' : 'text-green-700'">
                      <i :class="p.locations[0].type === 'outlet' ? 'fa fa-store' : 'fa fa-warehouse'" class="mr-1"></i>
                      {{ p.locations[0].name }}
                    </span>
                  </template>
                  <!-- Multiple locations -->
                  <template v-else>
                    <div class="flex flex-wrap gap-1">
                      <span v-for="(loc, idx) in p.locations" :key="idx" 
                        class="text-xs font-semibold px-2 py-1 rounded border"
                        :class="loc.type === 'outlet' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-green-700 bg-green-50 border-green-200'">
                        <i :class="loc.type === 'outlet' ? 'fa fa-store' : 'fa fa-warehouse'" class="mr-1"></i>
                        {{ loc.name }}
                      </span>
                    </div>
                  </template>
                </div>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-6 py-3">{{ p.payment_type }}</td>
              <td class="px-6 py-3">
                <div v-if="p.invoice_numbers && p.invoice_numbers.length > 0" class="flex flex-wrap gap-1">
                  <span v-for="(invoice, idx) in p.invoice_numbers" :key="idx" class="text-xs font-mono text-blue-700 bg-blue-50 px-2 py-1 rounded border border-blue-200">
                    {{ invoice }}
                  </span>
                </div>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-6 py-3">{{ formatCurrency(p.total) }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': p.status === 'draft',
                  'bg-green-100 text-green-700': p.status === 'approved' || p.status === 'paid',
                  'bg-red-100 text-red-700': p.status === 'rejected',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ p.status }}
                </span>
              </td>
              <td class="px-6 py-3">{{ p.creator?.nama_lengkap }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="goToDetail(p.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button v-if="p.status === 'approved'" @click="markAsPaid(p.id)" class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-check-circle mr-1"></i> Paid
                  </button>
                  <button @click="goToEdit(p.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-pencil-alt mr-1"></i> Edit
                  </button>
                  <button @click="confirmDelete(p.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
            </template>
          </tbody>
        </table>
        </div>
      </template>
      <!-- Pagination -->
      <div v-if="isDataLoaded && safePayments && safePayments.links && safePayments.links.length > 3" class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in safePayments.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
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
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLoading } from '@/Composables/useLoading';

const props = defineProps({
  payments: Object,
  filters: Object,
  dataLoaded: {
    type: Boolean,
    default: false
  }
});

const { showLoading, hideLoading } = useLoading();

// Get today's date for default filter
const getTodayDate = () => {
  const today = new Date();
  return today.toISOString().split('T')[0];
};

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  date_from: props.filters?.date_from || getTodayDate(),
  date_to: props.filters?.date_to || getTodayDate(),
  per_page: props.filters?.per_page || 10
});

const isDataLoaded = ref(props.dataLoaded || false);
const isLoading = ref(false);

// Legacy refs for backward compatibility
const search = computed({
  get: () => filters.value.search,
  set: (val) => { filters.value.search = val; }
});
const selectedStatus = computed({
  get: () => filters.value.status,
  set: (val) => { filters.value.status = val; }
});
const from = computed({
  get: () => filters.value.date_from,
  set: (val) => { filters.value.date_from = val; }
});
const to = computed({
  get: () => filters.value.date_to,
  set: (val) => { filters.value.date_to = val; }
});

// Ensure payments is always an object with data property
const safePayments = computed(() => {
  if (!props.payments || typeof props.payments !== 'object') {
    return {
      data: [],
      links: [],
      current_page: 1,
      last_page: 1,
      total: 0,
      per_page: 10
    };
  }
  return {
    ...props.payments,
    data: Array.isArray(props.payments.data) ? props.payments.data : [],
    links: Array.isArray(props.payments.links) ? props.payments.links : [],
    current_page: props.payments.current_page || 1,
    last_page: props.payments.last_page || 1,
    total: props.payments.total || 0,
    per_page: props.payments.per_page || 10
  };
});

watch(
  () => props.filters,
  (newFilters) => {
    if (newFilters) {
      filters.value.search = newFilters.search || '';
      filters.value.status = newFilters.status || '';
      filters.value.date_from = newFilters.date_from || getTodayDate();
      filters.value.date_to = newFilters.date_to || getTodayDate();
      filters.value.per_page = newFilters.per_page || 10;
    }
  },
  { immediate: true }
);

watch(
  () => props.dataLoaded,
  (loaded) => {
    isDataLoaded.value = loaded || false;
  },
  { immediate: true }
);

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function loadData() {
  isLoading.value = true;
  showLoading('Memuat data Food Payment...');
  
  const params = {
    load_data: true,
    search: filters.value.search,
    status: filters.value.status,
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
    per_page: filters.value.per_page
  };
  
  router.get('/food-payments', params, {
    preserveState: true,
    replace: true,
    onFinish: () => {
      isLoading.value = false;
      hideLoading();
    }
  });
}

// Load data automatically when pagination is used
function goToPage(url) {
  if (url) {
    isLoading.value = true;
    showLoading('Memuat data Food Payment...');
    router.visit(url, { 
      preserveState: true, 
      replace: true,
      onFinish: () => {
        isLoading.value = false;
        hideLoading();
      }
    });
  }
}

function goToEdit(id) {
  router.visit(`/food-payments/${id}/edit`);
}

function goToDetail(id) {
  router.visit(`/food-payments/${id}`);
}

function confirmDelete(id) {
  if (!id) return;
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Hapus Food Payment?',
      text: 'Data yang dihapus tidak dapat dikembalikan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        showLoading('Menghapus Food Payment...');
        router.delete(`/food-payments/${id}`, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Food Payment berhasil dihapus!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menghapus Food Payment', 'error');
          },
          onFinish: () => {
            hideLoading();
          }
        });
      }
    });
  });
}

async function markAsPaid(id) {
  if (!id) return;
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Tandai sebagai Paid?',
      text: 'Food Payment akan ditandai sebagai paid.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10B981',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Tandai Paid',
      cancelButtonText: 'Batal'
    }).then(async (result) => {
      if (result.isConfirmed) {
        showLoading('Menandai sebagai Paid...');
        try {
          const response = await fetch(`/food-payments/${id}/mark-as-paid`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
          });
          
          const data = await response.json();
          
          if (data.success) {
            Swal.fire('Berhasil', data.message || 'Food Payment berhasil ditandai sebagai paid!', 'success');
            router.reload({ only: ['payments'] });
          } else {
            Swal.fire('Gagal', data.message || 'Gagal menandai Food Payment sebagai paid', 'error');
          }
        } catch (error) {
          console.error('Error marking as paid:', error);
          Swal.fire('Gagal', 'Gagal menandai Food Payment sebagai paid', 'error');
        } finally {
          hideLoading();
        }
      }
    });
  });
}
</script> 