<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-file-invoice"></i> Contra Bon
        </h1>
        <Link
          :href="route('contra-bons.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Contra Bon
        </Link>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <!-- Search Box -->
        <div class="flex-1 min-w-64">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Cari nomor/PO/Supplier..."
            class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          />
        </div>
        
        <!-- Filters -->
        <select v-model="filters.status" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
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
          <p class="text-gray-600 mb-4">Klik tombol "Load Data" untuk memuat data Contra Bon</p>
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
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Sumber</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO/Retail</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Source Numbers</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No Invoice Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!safeContraBons.data || !safeContraBons.data.length">
              <td colspan="12" class="text-center py-10 text-gray-400">Belum ada data Contra Bon.</td>
            </tr>
            <template v-else>
              <tr v-for="cb in safeContraBons.data" :key="cb.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ cb.number }}</td>
              <td class="px-6 py-3">{{ formatDate(cb.date) }}</td>
              <td class="px-6 py-3">{{ cb.supplier?.name }}</td>
              <td class="px-6 py-3">
                <div v-if="cb.source_types && cb.source_types.length > 0" class="flex flex-wrap gap-1">
                  <span 
                    v-for="sourceType in cb.source_types" 
                    :key="sourceType"
                    :class="{
                      'bg-blue-100 text-blue-700 border border-blue-300': sourceType === 'PR Foods',
                      'bg-green-100 text-green-700 border border-green-300': sourceType === 'RO Supplier',
                      'bg-purple-100 text-purple-700 border border-purple-300': sourceType === 'Retail Food',
                      'bg-orange-100 text-orange-700 border border-orange-300': sourceType === 'Warehouse Retail Food',
                      'bg-gray-100 text-gray-700 border border-gray-300': sourceType === 'Unknown',
                    }" 
                    class="px-2 py-1 rounded-full text-xs font-semibold">
                    {{ sourceType === 'PR Foods' ? 'PRF' : sourceType === 'Retail Food' ? 'RF' : sourceType === 'Warehouse Retail Food' ? 'RWF' : sourceType }}
                  </span>
                </div>
                <span v-else :class="{
                  'bg-blue-100 text-blue-700': cb.source_type_display === 'PR Foods',
                  'bg-green-100 text-green-700': cb.source_type_display === 'RO Supplier',
                  'bg-purple-100 text-purple-700': cb.source_type_display === 'Retail Food',
                  'bg-orange-100 text-orange-700': cb.source_type_display === 'Warehouse Retail Food',
                  'bg-gray-100 text-gray-700': cb.source_type_display === 'Unknown',
                }" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ cb.source_type_display || (cb.source_type === 'purchase_order' ? 'PO/GR' : 'Retail Food') }}
                </span>
              </td>
              <td class="px-6 py-3">
                {{ cb.source_type === 'purchase_order' ? cb.purchase_order?.number : cb.retail_food?.retail_number || '-' }}
              </td>
              <td class="px-6 py-3">
                <div v-if="cb.source_numbers && cb.source_numbers.length > 0" class="flex flex-wrap gap-1">
                  <span v-for="number in cb.source_numbers" :key="number" 
                        :class="getSourceNumberBadgeClass(number, cb.source_types)"
                        class="text-xs px-2 py-1 rounded-full font-semibold border">
                    {{ number }}
                  </span>
                </div>
                <span v-else class="text-gray-400 text-sm">-</span>
              </td>
              <td class="px-6 py-3">
                <div v-if="cb.source_outlets && cb.source_outlets.length > 0" class="flex flex-wrap gap-1">
                  <span v-for="outlet in cb.source_outlets" :key="outlet" 
                        class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                    {{ outlet }}
                  </span>
                </div>
                <span v-else class="text-gray-400 text-sm">-</span>
              </td>
              <td class="px-6 py-3">{{ cb.supplier_invoice_number || '-' }}</td>
              <td class="px-6 py-3">{{ formatCurrency(cb.total_amount) }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': cb.status === 'draft',
                  'bg-green-100 text-green-700': cb.status === 'approved',
                  'bg-red-100 text-red-700': cb.status === 'rejected',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ cb.status }}
                </span>
              </td>
              <td class="px-6 py-3">{{ cb.creator?.nama_lengkap }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="goToDetail(cb.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button 
                    v-if="canEdit"
                    @click="goToEdit(cb.id)" 
                    class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition"
                  >
                    <i class="fa fa-pencil-alt mr-1"></i> Edit
                  </button>
                  <button @click="confirmDelete(cb.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
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
      <div v-if="isDataLoaded && safeContraBons && safeContraBons.links && safeContraBons.links.length > 3" class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in safeContraBons.links"
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
import { ref, watch, onMounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLoading } from '@/Composables/useLoading';

const props = defineProps({
  contraBons: Object,
  filters: Object,
  dataLoaded: {
    type: Boolean,
    default: false
  }
});

const page = usePage();
const { showLoading, hideLoading } = useLoading();

// Check if user can edit (Finance Manager or Superadmin)
const canEdit = computed(() => {
  const user = page.props.auth?.user;
  if (!user) return false;
  const isFinanceManager = user.id_jabatan == 160 && user.status == 'A';
  const isSuperadmin = user.id_role === '5af56935b011a' && user.status === 'A';
  return isFinanceManager || isSuperadmin;
});

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

// Ensure contraBons is always an object with data property
const safeContraBons = computed(() => {
  if (!props.contraBons || typeof props.contraBons !== 'object') {
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
    ...props.contraBons,
    data: Array.isArray(props.contraBons.data) ? props.contraBons.data : [],
    links: Array.isArray(props.contraBons.links) ? props.contraBons.links : [],
    current_page: props.contraBons.current_page || 1,
    last_page: props.contraBons.last_page || 1,
    total: props.contraBons.total || 0,
    per_page: props.contraBons.per_page || 10
  };
});

// Legacy refs for backward compatibility (if needed)
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

function getSourceNumberBadgeClass(number, sourceTypes) {
  if (!number) return 'bg-gray-100 text-gray-800 border-gray-300';
  
  const numStr = String(number).toUpperCase();
  
  // Deteksi berdasarkan prefix number
  if (numStr.startsWith('PRF-')) {
    return 'bg-blue-100 text-blue-800 border-blue-300';
  } else if (numStr.startsWith('RWF')) {
    return 'bg-orange-100 text-orange-800 border-orange-300';
  } else if (numStr.startsWith('RF')) {
    return 'bg-purple-100 text-purple-800 border-purple-300';
  }
  
  // Fallback: gunakan source_types jika tersedia
  if (sourceTypes && sourceTypes.length > 0) {
    // Jika ada multiple source types, gunakan yang pertama atau yang paling cocok
    if (sourceTypes.includes('PR Foods')) {
      return 'bg-blue-100 text-blue-800 border-blue-300';
    } else if (sourceTypes.includes('Warehouse Retail Food')) {
      return 'bg-orange-100 text-orange-800 border-orange-300';
    } else if (sourceTypes.includes('Retail Food')) {
      return 'bg-purple-100 text-purple-800 border-purple-300';
    } else if (sourceTypes.includes('RO Supplier')) {
      return 'bg-green-100 text-green-800 border-green-300';
    }
  }
  
  // Default
  return 'bg-gray-100 text-gray-800 border-gray-300';
}

function loadData() {
  isLoading.value = true;
  showLoading('Memuat data Contra Bon...');
  
  const params = {
    load_data: true,
    search: filters.value.search,
    status: filters.value.status,
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
    per_page: filters.value.per_page
  };
  
  router.get('/contra-bons', params, {
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
    showLoading('Memuat data Contra Bon...');
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

// goToPage sudah di-update di atas

function goToEdit(id) {
  router.visit(`/contra-bons/${id}/edit`);
}

function goToDetail(id) {
  router.visit(`/contra-bons/${id}`);
}

function confirmDelete(id) {
  if (!id) return;
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Hapus Contra Bon?',
      text: 'Data yang dihapus tidak dapat dikembalikan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        showLoading('Menghapus Contra Bon...');
        router.delete(`/contra-bons/${id}`, {
          onSuccess: (page) => {
            // Use message from flash or default message
            const message = page.props.flash?.success || 'Contra Bon berhasil dihapus!';
            Swal.fire('Berhasil', message, 'success');
          },
          onError: (errors) => {
            // Use error message from flash or default message
            const message = page.props.flash?.error || errors?.message || 'Gagal menghapus Contra Bon';
            Swal.fire('Gagal', message, 'error');
          },
          onFinish: () => {
            hideLoading();
          }
        });
      }
    });
  });
}

// Handle flash messages on page load
onMounted(() => {
  import('sweetalert2').then(({ default: Swal }) => {
    if (page.props.flash?.success) {
      Swal.fire('Berhasil', page.props.flash.success, 'success');
    }
    if (page.props.flash?.error) {
      Swal.fire('Gagal', page.props.flash.error, 'error');
    }
  });
});
</script> 